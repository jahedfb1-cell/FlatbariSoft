<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Born from a real incident: two XAMPP installs (C:\xampp and C:\xampp2)
 * both want port 3306, and starting the wrong one made the app connect to
 * an old empty snapshot while the real data sat untouched in the other.
 * Nothing in the app could say *which* MySQL it was talking to. This does.
 */
class DatabaseWhere extends Command
{
    protected $signature = 'db:where {--write : Also save the report to DATABASE-STATUS.txt in the project root}';

    protected $description = 'Show which database this app is connected to, where its files live, and whether it holds real data';

    /** Tables whose row counts tell "real data" apart from "empty schema". */
    private const KEY_TABLES = ['flats', 'persons', 'users', 'bills', 'payments', 'expenses', 'journal_entries'];

    public function handle(): int
    {
        $out = [];
        $name = config('database.default');
        $cfg = config("database.connections.{$name}");
        $driver = $cfg['driver'] ?? '?';
        $database = $cfg['database'] ?? '?';

        $out[] = 'Green Valley BMS - database location report ('.now()->format('Y-m-d H:i:s').' '.config('app.timezone').')';
        $out[] = str_repeat('=', 64);
        $out[] = "Connection : {$name} ({$driver})";
        $out[] = 'Host:port   : '.($cfg['host'] ?? '-').':'.($cfg['port'] ?? '-');
        $out[] = "Database    : {$database}";

        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            $out[] = 'Status      : NOT REACHABLE - '.strtok($e->getMessage(), "\n");
            $out[] = '';
            $out[] = 'The MySQL server is not running (or is not on that port).';
            $out[] = 'Run find-database.bat in the project root - it lists every MySQL install on';
            $out[] = 'this PC and tells you which one holds the real data and how to start it.';

            return $this->finish($out, self::FAILURE);
        }

        $out[] = 'Status      : CONNECTED';

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $info = DB::selectOne('SELECT @@version AS version, @@datadir AS datadir, @@basedir AS basedir');
            $out[] = "Server      : {$info->version}";
            $out[] = "Install dir : {$info->basedir}";
            $out[] = "Data dir    : {$info->datadir}";

            $folder = rtrim($info->datadir, '\\/').DIRECTORY_SEPARATOR.$database;
            $out[] = "DB folder   : {$folder}";
            if (is_dir($folder)) {
                $files = glob($folder.DIRECTORY_SEPARATOR.'*') ?: [];
                $newest = $files ? max(array_map('filemtime', $files)) : null;
                $out[] = 'Last write  : '.($newest ? date('Y-m-d H:i:s', $newest).' '.date_default_timezone_get() : 'n/a');
            }

            $size = DB::selectOne(
                'SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS mb FROM information_schema.tables WHERE table_schema = ?',
                [$database],
            );
            $out[] = 'Size        : '.($size->mb ?? 0).' MB';
        }

        $applied = $this->safeCount('migrations');
        $files = count(glob(database_path('migrations/*.php')) ?: []);
        $out[] = '';
        $out[] = "Migrations  : {$applied} applied of {$files} files".($applied < $files ? '  <-- PENDING, run: php artisan migrate' : '');

        $out[] = '';
        $out[] = 'Row counts:';
        $counts = [];
        foreach (self::KEY_TABLES as $table) {
            $counts[$table] = $this->safeCount($table);
            $out[] = '  '.str_pad($table, 17).($counts[$table] === null ? '(table missing)' : $counts[$table]);
        }

        $out[] = '';
        if (($counts['flats'] ?? 0) === 0 && ($counts['bills'] ?? 0) === 0) {
            $out[] = 'VERDICT: EMPTY - this server has the tables but no business data.';
            $out[] = 'If you expect flats/bills here, you are most likely connected to the WRONG';
            $out[] = 'MySQL install. Run find-database.bat to find the one that has the data.';
        } else {
            $out[] = 'VERDICT: DATA PRESENT - this looks like the real database.';
        }

        return $this->finish($out, self::SUCCESS);
    }

    private function safeCount(string $table): ?int
    {
        try {
            return DB::table($table)->count();
        } catch (Throwable) {
            return null;
        }
    }

    private function finish(array $lines, int $code): int
    {
        foreach ($lines as $line) {
            $this->line($line);
        }

        if ($this->option('write')) {
            file_put_contents(base_path('DATABASE-STATUS.txt'), implode(PHP_EOL, $lines).PHP_EOL);
            $this->newLine();
            $this->info('Saved to DATABASE-STATUS.txt');
        }

        return $code;
    }
}
