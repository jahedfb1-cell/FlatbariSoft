<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * A full copy of the database as plain SQL, produced by PHP alone (the shared host has no shell access
 * and mysqldump may not exist). Streamed row by row in chunks, so a big database never has to fit in memory.
 * Restoring is "import this file" in phpMyAdmin. It contains everything, password hashes included - the
 * download is for the admin only (backup.manage) and every download is recorded in the audit log.
 */
class BackupService
{
    /** @param  callable(string): void  $write */
    public function dump(callable $write): void
    {
        $db = DB::connection();
        $mysql = $db->getDriverName() === 'mysql';
        $quote = fn (string $name) => $mysql ? '`'.str_replace('`', '``', $name).'`' : '"'.str_replace('"', '""', $name).'"';

        $write("-- Green Valley BMS backup, ".now('Asia/Dhaka')->format('Y-m-d H:i:s')." (Asia/Dhaka)\n-- Restore: phpMyAdmin > Import this file into an empty database.\n\n");
        if ($mysql) {
            $write("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");
        }

        foreach ($this->tables($db, $mysql) as $table) {
            $q = $quote($table);
            $write("DROP TABLE IF EXISTS {$q};\n".$this->createStatement($db, $mysql, $table, $q).";\n\n");

            $db->table($table)->orderByRaw('1')->chunk(500, function ($rows) use ($write, $q, $quote, $db) {
                foreach ($rows as $row) {
                    $row = (array) $row;
                    $cols = implode(',', array_map($quote, array_keys($row)));
                    $vals = implode(',', array_map(fn ($v) => $v === null ? 'NULL' : $db->getPdo()->quote((string) $v), $row));
                    $write("INSERT INTO {$q} ({$cols}) VALUES ({$vals});\n");
                }
            });
            $write("\n");
        }

        if ($mysql) {
            $write("SET FOREIGN_KEY_CHECKS=1;\n");
        }
    }

    /** @return list<string> */
    private function tables($db, bool $mysql): array
    {
        $rows = $mysql
            ? $db->select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')
            : $db->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");

        return array_map(fn ($r) => (string) array_values((array) $r)[0], $rows);
    }

    private function createStatement($db, bool $mysql, string $table, string $q): string
    {
        if ($mysql) {
            $row = (array) $db->selectOne("SHOW CREATE TABLE {$q}");

            return (string) ($row['Create Table'] ?? array_values($row)[1]);
        }

        return (string) $db->selectOne('SELECT sql FROM sqlite_master WHERE name = ?', [$table])->sql;
    }
}
