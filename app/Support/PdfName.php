<?php

namespace App\Support;

use App\Models\Flat;

/**
 * The name a PDF is saved under. Everything for a flat owner is "flat no-owner name-what the money was for", e.g.
 * "10A-Nazrul Islam-Service Charge + Gas Bill Sep 2026.pdf"; every other document is "what it is-which period/who".
 * The result is safe on Windows, Android and macOS (no \ / : * ? " < > |), and a Bengali name is kept as it is.
 */
class PdfName
{
    public const MONTHS = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    /** One part of a name: illegal characters out, spaces tidied, never empty-looking or absurdly long. */
    public static function clean(?string $part): string
    {
        $part = (string) preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/u', ' ', (string) $part);
        $part = trim((string) preg_replace('/\s+/u', ' ', $part), " .\t");

        return mb_substr($part, 0, 70);
    }

    /** "Sep 2026" */
    public static function month(int $year, int $month): string
    {
        return (self::MONTHS[$month] ?? (string) $month).' '.$year;
    }

    /** Joins the parts that exist with "-" and adds .pdf (or another extension). */
    public static function make(?string ...$parts): string
    {
        $clean = array_values(array_filter(array_map([self::class, 'clean'], $parts), fn ($p) => $p !== ''));

        return ($clean ? implode('-', $clean) : 'document').'.pdf';
    }

    /**
     * The PDF's own title is its name too: the browser's tab, its print dialog ("Save as PDF") and a saved copy all start
     * from it, even when the file was opened from a temporary address that has no file name.
     */
    public static function titled(\Barryvdh\DomPDF\PDF $pdf, string $filename): \Barryvdh\DomPDF\PDF
    {
        $pdf->addInfo(['Title' => pathinfo($filename, PATHINFO_FILENAME)]);

        return $pdf;
    }

    /** A Content-Disposition value for a PDF that is built by hand (the name is encoded, so Bengali is safe). */
    public static function disposition(string $filename, string $kind = 'inline'): string
    {
        $fallback = trim((string) preg_replace('/[^A-Za-z0-9._ -]+/', '_', \Illuminate\Support\Str::ascii($filename)));

        return \Symfony\Component\HttpFoundation\HeaderUtils::makeDisposition($kind, $filename, $fallback !== '' ? $fallback : 'document.pdf');
    }

    /** "2026-09-01 to 2026-09-30", or "All dates". */
    public static function range(?string $from, ?string $to): string
    {
        return $from && $to ? "{$from} to {$to}" : ($from ? "from {$from}" : ($to ? "until {$to}" : 'All dates'));
    }

    /** The flat owners' names as they print on a document: "Nazrul Islam" or "Nazrul Islam & Mahfuza Arzu". */
    public static function ownerOf(Flat $flat): ?string
    {
        $flat->loadMissing('currentOwners.person');

        return $flat->occupantNames()['owner'] ? str_replace(', ', ' & ', $flat->occupantNames()['owner']) : null;
    }

    /** flat no-owner name-purpose. */
    public static function forOwner(Flat $flat, string $purpose): string
    {
        return self::make($flat->flat_no, self::ownerOf($flat), $purpose);
    }

    /** The same for several flats of one owner (the resident's own ledger): "10A+9C-Name-Purpose". */
    public static function forOwnerFlats(array $flatNos, ?string $owner, string $purpose): string
    {
        return self::make(implode('+', array_unique($flatNos)), $owner, $purpose);
    }
}
