<?php

namespace App\Services;

/**
 * dompdf ships with DejaVu Sans, which is missing the Bengali currency
 * sign (৳) and cannot correctly *shape* Bengali script at all (no
 * GSUB/GPOS support — pre-base vowel signs render in the wrong visual
 * position). Report PDFs are therefore English-labelled by design (see
 * ReportController), but still need this font for the ৳ symbol and any
 * Bengali digits/text that appear in amounts, item descriptions, and
 * the letterhead.
 *
 * "Noto Sans Bengali" (Google/SIL Open Font License — freely
 * redistributable, see resources/fonts/NotoSansBengali-OFL.txt) is
 * embedded as a base64 data: URI, both as normal and bold weight, so
 * bold-styled elements (headings, table headers) don't fall back to a
 * font with no Bengali glyphs at all. dompdf's own font-family cache
 * doesn't reliably pick up a plain @font-face without this. Lives
 * under resources/ (a real source asset), never storage/ — every
 * storage/app/* subtree is gitignored by default in Laravel, so a font
 * placed there would silently never reach a fresh clone, breaking PDF
 * export invisibly until someone opened one (tried storage/app/fonts
 * and storage/app/private/fonts here first — both are ignored).
 */
class PdfFontService
{
    private static ?string $css = null;

    public static function fontFaceCss(): string
    {
        if (self::$css !== null) {
            return self::$css;
        }

        $font = base64_encode(file_get_contents(resource_path('fonts/NotoSansBengali.ttf')));

        return self::$css = <<<CSS
            @font-face { font-family: "Noto"; src: url(data:font/ttf;base64,{$font}) format("truetype"); font-weight: normal; }
            @font-face { font-family: "Noto"; src: url(data:font/ttf;base64,{$font}) format("truetype"); font-weight: bold; }
            CSS;
    }
}
