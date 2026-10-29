<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    {!! \App\Services\PdfFontService::fontFaceCss() !!}

    @page { size: A4; margin: 14mm 11mm; }
    body { font-family: "Noto", "DejaVu Sans", sans-serif; font-size: 9px; color: #000; }
    h1 { font-size: 15px; margin: 0 0 1px; }
    h2 { font-size: 10.5px; margin: 11px 0 4px; }
    .head { text-align: center; border-bottom: 2px solid #000; padding-bottom: 5px; margin-bottom: 7px; }
    .muted { color: #444; font-size: 8.5px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
    th, td { border: 1px solid #000; padding: 2px 4px; text-align: left; vertical-align: top; }
    th { background: #e6e6e6; text-align: center; }
    td.num, th.num { text-align: right; white-space: nowrap; }
    tr.total td { font-weight: bold; background: #f0f0f0; }
    tr.sub td { background: #f7f7f7; }
    table.kv th { width: 50%; text-align: left; }
    .sign { margin-top: 28px; display: table; width: 100%; }
    .sign div { display: table-cell; width: 33%; text-align: center; font-size: 8.5px; }
    .sign span { display: inline-block; border-top: 1px solid #000; padding: 2px 14px; }
    .foot { margin-top: 5px; font-size: 8px; color: #444; text-align: right; }
    .pagebreak { page-break-before: always; }
</style>
</head>
<body>
@include('reports.partials.fund-statement-body', ['r' => $report, 'building' => $building])

<div class="sign"><div><span>Prepared by</span></div><div><span>Treasurer</span></div><div><span>Secretary / President</span></div></div>
<div class="foot">Printed {{ now()->format('d M Y, h:i A') }} — generated from the cash and bank ledgers</div>
</body>
</html>
