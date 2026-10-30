<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    {!! \App\Services\PdfFontService::fontFaceCss() !!}
    @page { size: A4 landscape; margin: 12mm 10mm; }
    body { font-family: "Noto", "DejaVu Sans", sans-serif; font-size: 8.5px; color: #000; }
    h1 { font-size: 14px; margin: 0 0 2px; }
    h2 { font-size: 10.5px; margin: 10px 0 4px; }
    .head { text-align: center; border-bottom: 2px solid #000; padding-bottom: 5px; margin-bottom: 7px; }
    .muted { color: #444; font-size: 8.5px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
    th, td { border: 1px solid #000; padding: 2px 4px; text-align: left; }
    th { background: #e6e6e6; text-align: center; }
    td.num, th.num { text-align: right; white-space: nowrap; }
    tr.total td { font-weight: bold; background: #f0f0f0; }
    .over { font-weight: bold; }
    .foot { margin-top: 5px; font-size: 8px; color: #444; text-align: right; }
    .pagebreak { page-break-before: always; }
</style>
</head>
<body>
@php
    $f = fn ($v) => number_format((float) $v, 0);
    $pct = fn ($v) => $v === null ? '-' : ($v > 0 ? '+' : '').$v.'%';
    $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    $t = $r['totals'];
@endphp

<div class="head">
    <h1>{{ $building->name ?? 'Green Valley' }}</h1>
    <div><b>Budget vs Actual</b> — Fiscal year {{ $r['label'] }}</div>
    <div class="muted">{{ date('d M Y', strtotime($r['from'])) }} to {{ date('d M Y', strtotime($r['to'])) }} · actual = approved expenses only</div>
</div>

<table>
    <tr>
        <th style="text-align:left">Expense head</th>
        <th>Budget {{ $r['label'] }}</th><th>Spent so far</th><th>Remaining</th><th>Used</th>
        <th>Spent {{ $r['last_label'] }}</th><th>Change vs last year</th>
    </tr>
    @foreach ($r['rows'] as $row)
        <tr>
            <td>{{ $row['name_en'] ?: $row['name_bn'] }}</td>
            <td class="num">{{ $f($row['budget']) }}</td>
            <td class="num">{{ $f($row['spent']) }}</td>
            <td class="num {{ $row['over'] ? 'over' : '' }}">{{ $f($row['remaining']) }}{{ $row['over'] ? ' (over)' : '' }}</td>
            <td class="num">{{ $row['percent'] === null ? '-' : $row['percent'].'%' }}</td>
            <td class="num">{{ $f($row['last_spent']) }}</td>
            <td class="num">{{ $pct($row['spent_change_percent']) }}</td>
        </tr>
    @endforeach
    <tr class="total">
        <td>Total</td>
        <td class="num">{{ $f($t['budget']) }}</td><td class="num">{{ $f($t['spent']) }}</td><td class="num">{{ $f($t['remaining']) }}</td>
        <td class="num">{{ $t['percent'] === null ? '-' : $t['percent'].'%' }}</td>
        <td class="num">{{ $f($t['last_spent']) }}</td><td class="num">{{ $pct($t['spent_change_percent']) }}</td>
    </tr>
</table>

<h2>Month by month — spent</h2>
<table>
    <tr>
        <th style="text-align:left">Expense head</th>
        @foreach ($r['months'] as $m)<th>{{ $monthNames[$m['month'] - 1] }} {{ substr((string) $m['year'], 2) }}</th>@endforeach
        <th>Year</th>
    </tr>
    @foreach ($r['rows'] as $row)
        @if (bccomp($row['spent'], '0', 4) > 0)
            <tr>
                <td>{{ $row['name_en'] ?: $row['name_bn'] }}</td>
                @foreach ($row['monthly'] as $v)<td class="num">{{ bccomp($v, '0', 4) === 0 ? '' : $f($v) }}</td>@endforeach
                <td class="num">{{ $f($row['spent']) }}</td>
            </tr>
        @endif
    @endforeach
    <tr class="total">
        <td>Total</td>
        @foreach ($r['months'] as $m)<td class="num">{{ $f($m['spent']) }}</td>@endforeach
        <td class="num">{{ $f($t['spent']) }}</td>
    </tr>
</table>
<div class="foot">Printed {{ now('Asia/Dhaka')->format('d M Y, h:i A') }} — worked out from the approved expenses</div>
</body>
</html>
