<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    {!! \App\Services\PdfFontService::fontFaceCss() !!}
    @page { margin: 10mm; }
    body { font-family: "Noto", "DejaVu Sans", sans-serif; font-size: 10px; color: #000; }
    h1 { font-size: 15px; margin: 0; text-align: center; }
    h2 { font-size: 13px; margin: 2px 0; text-align: center; }
    .sub { text-align: center; color: #333; }
    .period { text-align: center; font-weight: bold; margin: 3px 0 8px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #000; padding: 3px 5px; vertical-align: top; }
    th { background: #eee; }
    td.num, th.num { text-align: right; white-space: nowrap; }
    tr.total td { font-weight: bold; background: #f2f2f2; }
    .summary { width: auto; margin: 0 auto 10px; }
    .summary td { border: 1px solid #999; padding: 3px 10px; }
    .summary td.k { background: #f6f6f6; font-weight: bold; }
    .foot { margin-top: 8px; font-size: 8px; color: #555; text-align: right; }
</style>
</head>
<body>
@php($f = fn ($v) => number_format((float) $v, 2))
<h1>{{ $building->name ?? 'Green Valley' }}</h1>
<h2>{{ $r['title'] }}</h2>
<div class="sub">{{ $r['subtitle'] }}</div>
<div class="period">{{ $r['period'] }}</div>

<table class="summary">
@foreach ($r['summary'] as $s)
    <tr><td class="k">{{ $s['label'] }}</td><td class="num">{{ ($s['type'] ?? '') === 'money' ? $f($s['value']) : $s['value'] }}</td></tr>
@endforeach
</table>

<table>
    <thead>
        <tr>
        @foreach ($r['columns'] as $c)
            <th class="{{ $c['type'] === 'money' ? 'num' : '' }}">{{ $c['label'] }}</th>
        @endforeach
        </tr>
    </thead>
    <tbody>
    @forelse ($r['rows'] as $row)
        <tr>
        @foreach ($r['columns'] as $c)
            <td class="{{ $c['type'] === 'money' ? 'num' : '' }}">{{ $c['type'] === 'money' && ($row[$c['key']] ?? '') !== '' ? $f($row[$c['key']]) : ($row[$c['key']] ?? '') }}</td>
        @endforeach
        </tr>
    @empty
        <tr><td colspan="{{ count($r['columns']) }}" style="text-align:center">কোনো তথ্য নেই</td></tr>
    @endforelse
    @if ($r['totals'] !== [])
        <tr class="total">
        @foreach ($r['columns'] as $i => $c)
            <td class="{{ $c['type'] === 'money' ? 'num' : '' }}">{{ $i === 0 ? 'মোট' : (isset($r['totals'][$c['key']]) ? $f($r['totals'][$c['key']]) : '') }}</td>
        @endforeach
        </tr>
    @endif
    </tbody>
</table>
<div class="foot">তৈরি: {{ now('Asia/Dhaka')->format('d/m/Y h:i A') }}</div>
</body>
</html>
