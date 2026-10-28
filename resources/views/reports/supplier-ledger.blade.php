<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    {!! \App\Services\PdfFontService::fontFaceCss() !!}
    @page { size: A4; margin: 15mm 12mm; }
    body { font-family: "Noto", "DejaVu Sans", sans-serif; font-size: 9.5px; color: #000; }
    h1 { font-size: 15px; margin: 0 0 2px; }
    .head { text-align: center; border-bottom: 2px solid #000; padding-bottom: 6px; margin-bottom: 8px; }
    .muted { color: #444; font-size: 9px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    th, td { border: 1px solid #000; padding: 3px 5px; text-align: left; }
    th { background: #e6e6e6; }
    td.num, th.num { text-align: right; }
    tr.total td { font-weight: bold; background: #f0f0f0; }
    .foot { margin-top: 6px; font-size: 8.5px; color: #444; text-align: right; }
</style>
</head>
<body>
@php($t = $ledger['totals'])
<div class="head">
    <h1>{{ $building->name ?? 'Green Valley' }}</h1>
    <div>Supplier Ledger — {{ $ledger['kind'] === 'materials' ? 'Construction material' : 'Gas' }}</div>
    <div><b>{{ $ledger['supplier'] }}</b></div>
    <div class="muted">
        Period:
        {{ $ledger['from'] ? date('d M Y', strtotime($ledger['from'])) : 'beginning' }}
        to
        {{ $ledger['to'] ? date('d M Y', strtotime($ledger['to'])) : 'today' }}
    </div>
</div>

<table>
    <tr>
        <th>Date</th><th>Ref</th><th>Particulars</th><th>Voucher</th>
        <th class="num">Purchase (Cr)</th><th class="num">Paid (Dr)</th><th class="num">Balance owed</th>
    </tr>
    <tr>
        <td colspan="6"><i>Opening balance</i></td>
        <td class="num">{{ number_format((float) $ledger['opening'], 2) }}</td>
    </tr>
    @foreach ($ledger['rows'] as $r)
        <tr>
            <td>{{ date('d M Y', strtotime($r['date'])) }}</td>
            <td>{{ $r['ref'] }}</td>
            <td>{{ $r['particulars'] }}</td>
            <td>{{ $r['voucher_no'] ?: '-' }}</td>
            <td class="num">{{ (float) $r['credit'] ? number_format((float) $r['credit'], 2) : '' }}</td>
            <td class="num">{{ (float) $r['debit'] ? number_format((float) $r['debit'], 2) : '' }}</td>
            <td class="num">{{ number_format((float) $r['balance'], 2) }}</td>
        </tr>
    @endforeach
    <tr class="total">
        <td colspan="4">Total</td>
        <td class="num">{{ number_format((float) $t['purchased'], 2) }}</td>
        <td class="num">{{ number_format((float) $t['paid'], 2) }}</td>
        <td class="num">{{ number_format((float) $t['closing'], 2) }}</td>
    </tr>
</table>
<div class="foot">Balance owed = bought but not yet paid (payment counts once the expense is approved).</div>
</body>
</html>
