<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    {!! \App\Services\PdfFontService::fontFaceCss() !!}

    @page { size: A4 landscape; margin: 12mm 10mm; }
    body { font-family: "Noto", "DejaVu Sans", sans-serif; font-size: 8.5px; color: #000; }
    h1 { font-size: 14px; margin: 0 0 2px; }
    .head { text-align: center; border-bottom: 2px solid #000; padding-bottom: 5px; margin-bottom: 8px; }
    .muted { color: #444; font-size: 8.5px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #000; padding: 2px 4px; text-align: left; }
    th { background: #e6e6e6; }
    td.num, th.num { text-align: right; }
    tr.total td { font-weight: bold; background: #f0f0f0; }
</style>
</head>
<body>
<div class="head">
    <h1>{{ $building->name ?? 'Green Valley' }}</h1>
    <div>Construction Cost Collection - Flat-wise Statement</div>
    <div class="muted">Default target {{ number_format((float) $report['default_target'], 2) }} per flat - printed {{ now()->format('d M Y') }}</div>
</div>

<table>
    <tr>
        <th>#</th><th>Flat</th><th>Owner</th>
        @foreach ($report['heads'] as $h)<th class="num">{{ $h['name_en'] }}</th>@endforeach
        <th class="num">Collected</th><th class="num">Target</th><th class="num">Due / (excess)</th>
    </tr>
    @foreach ($report['rows'] as $i => $r)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $r['flat_no'] }}</td>
            <td>{{ $r['owner'] ?: '-' }}</td>
            @foreach ($report['heads'] as $h)<td class="num">{{ (float) $r['heads'][$h['code']] ? number_format((float) $r['heads'][$h['code']], 0) : '-' }}</td>@endforeach
            <td class="num">{{ number_format((float) $r['collected'], 0) }}</td>
            <td class="num">{{ number_format((float) $r['target'], 0) }}{{ $r['has_own_target'] ? '*' : '' }}</td>
            <td class="num">{{ bccomp($r['balance'], '0', 4) === 0 ? '-' : (bccomp($r['balance'], '0', 4) > 0 ? number_format((float) $r['balance'], 0) : '('.number_format(abs((float) $r['balance']), 0).')') }}</td>
        </tr>
    @endforeach
    <tr class="total">
        <td colspan="3">Total</td>
        @foreach ($report['heads'] as $h)<td class="num">{{ number_format((float) $report['totals'][$h['code']], 0) }}</td>@endforeach
        <td class="num">{{ number_format((float) $report['totals']['collected'], 0) }}</td>
        <td class="num">{{ number_format((float) $report['totals']['target'], 0) }}</td>
        <td class="num">{{ number_format((float) $report['totals']['balance_due'], 0) }} / ({{ number_format((float) $report['totals']['excess'], 0) }})</td>
    </tr>
</table>
<div class="muted" style="margin-top:4px">* flat has its own target. (brackets) = paid more than the target.</div>
</body>
</html>
