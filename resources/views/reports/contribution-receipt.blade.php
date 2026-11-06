<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    {!! \App\Services\PdfFontService::fontFaceCss() !!}

    @page { size: A5 landscape; margin: 10mm 12mm; }
    body { font-family: "Noto", "DejaVu Sans", sans-serif; font-size: 11px; color: #000; }
    h1 { font-size: 17px; margin: 0 0 2px; }
    .head { text-align: center; border-bottom: 2px solid #000; padding-bottom: 6px; margin-bottom: 10px; }
    .title { font-size: 13px; font-weight: bold; letter-spacing: 1px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    th, td { border: 1px solid #000; padding: 4px 7px; text-align: left; }
    th { background: #eee; width: 26%; }
    td.num { text-align: right; }
    tr.total td, tr.total th { font-weight: bold; background: #f2f2f2; }
    .sign { margin-top: 26px; display: table; width: 100%; }
    .sign div { display: table-cell; width: 50%; text-align: center; font-size: 10px; }
    .sign span { display: inline-block; border-top: 1px solid #000; padding: 2px 22px; }
    .foot { margin-top: 8px; font-size: 9px; color: #444; text-align: right; }
</style>
</head>
<body>
@php($f = fn ($v) => number_format((float) $v, 2))
<div class="head">
    <h1>{{ $building->name ?? 'Green Valley' }}</h1>
    <div class="title">MONEY RECEIPT &mdash; {{ $book['name_en'] }}</div>
</div>

<table>
    <tr><th>Receipt no</th><td><b>{{ $entry['receipt_no'] }}</b></td><th>Date</th><td>{{ \Carbon\Carbon::parse($entry['date'])->format('d M Y') }}</td></tr>
    <tr>
        <th>Received from</th><td>{{ $ledger['owner'] ?: '-' }}</td>
        <th>Flat</th><td><b>{{ $ledger['flat_no'] }}</b></td>
    </tr>
    <tr>
        <th>Towards</th><td>{{ $entry['head_name_en'] }}</td>
        <th>Paid by</th><td>{{ $entry['method'] }}{{ $entry['reference_no'] ? ' / '.$entry['reference_no'] : '' }}</td>
    </tr>
</table>

<table>
    <tr class="total"><th>Total received (Taka)</th><td class="num">{{ $f($entry['amount']) }}</td></tr>
</table>

@if ($ledger['target'] !== null)
    <table>
        <tr><th>Total to be paid ({{ $book['name_en'] }})</th><td class="num">{{ $f($ledger['target']) }}</td><th>Paid up to this receipt</th><td class="num">{{ $f(bcsub((string) $ledger['target'], (string) $entry['balance_after'], 4)) }}</td></tr>
        <tr>
            <th>@if (bccomp((string) $entry['balance_after'], '0', 4) < 0)Paid in excess@else Balance due @endif</th>
            <td class="num"><b>{{ $f(abs((float) $entry['balance_after'])) }}</b></td><th></th><td></td>
        </tr>
    </table>
@endif

<div class="sign"><div><span>Received by</span></div><div><span>Authorised signature</span></div></div>
<div class="foot">Printed {{ now()->format('d M Y, h:i A') }}</div>
</body>
</html>
