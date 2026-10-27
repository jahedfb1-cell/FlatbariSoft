{{-- Shared A4 print styling for every report PDF. English-labelled by
     design (see PdfFontService for why) — the embedded font is only for
     the ৳ symbol, Bengali digits, and the building's name/address. --}}
<style>
    {!! \App\Services\PdfFontService::fontFaceCss() !!}

    @page { size: A4; margin: 20mm 15mm; }
    body { font-family: "Noto", "DejaVu Sans", sans-serif; font-size: 11px; color: #1a2b3c; }
    h1 { font-size: 16px; margin: 0 0 2px; }
    h2 { font-size: 13px; margin: 14px 0 6px; }
    .muted { color: #5a6b7c; font-size: 10px; }
    .letterhead { text-align: center; border-bottom: 2px solid #1a2b3c; padding-bottom: 8px; margin-bottom: 14px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    th, td { border: 1px solid #ccc; padding: 5px 8px; text-align: left; }
    th { background: #eef2f6; }
    td.num, th.num { text-align: right; }
    tr.total td { font-weight: bold; border-top: 2px solid #1a2b3c; }
    .status-ok { color: #1a7a3c; font-weight: bold; }
    .status-bad { color: #b3261e; font-weight: bold; }
    .footer { margin-top: 24px; font-size: 9px; color: #5a6b7c; text-align: right; }
</style>
