<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    {!! \App\Services\PdfFontService::fontFaceCss() !!}

    /* Strictly black on white / greys - printed on a mono printer for audits. */
    @page { size: A4; margin: 15mm 12mm; }
    body { font-family: "Noto", "DejaVu Sans", sans-serif; font-size: 9.5px; color: #000; }
    h1 { font-size: 15px; margin: 0 0 2px; }
    h2 { font-size: 12px; margin: 12px 0 5px; }
    .head { text-align: center; border-bottom: 2px solid #000; padding-bottom: 6px; margin-bottom: 8px; }
    .muted { color: #444; font-size: 9px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    th, td { border: 1px solid #000; padding: 3px 5px; text-align: left; }
    th { background: #e6e6e6; }
    td.num, th.num { text-align: right; }
    table.kv th { width: 30%; }
    tr.total td { font-weight: bold; background: #f0f0f0; }
    .photo { text-align: center; margin-top: 6px; }
    .photo img { border: 1px solid #000; }
    .foot { margin-top: 6px; font-size: 8.5px; color: #444; text-align: right; }
    table.ledger { table-layout: fixed; font-size: 8px; }
    table.ledger th, table.ledger td { padding: 2px 3px; word-wrap: break-word; }
    .newpage { page-break-before: always; }
</style>
</head>
<body>

@php($t = $ledger['totals'])
<div class="head">
    <h1>{{ $building->name ?? 'Green Valley' }}</h1>
    <div>Gas Purchase Ledger (B&amp;W)</div>
    <div class="muted">
        Period:
        {{ $ledger['from'] ? date('d M Y', strtotime($ledger['from'])) : 'beginning' }}
        to
        {{ $ledger['to'] ? date('d M Y', strtotime($ledger['to'])) : 'today' }}
    </div>
</div>

<table class="kv">
    <tr><th>Purchases in period</th><td>{{ $t['count'] }}</td></tr>
    <tr><th>Gas bought (kg)</th><td>{{ number_format((float) $t['quantity_kg'], 2) }}</td></tr>
    <tr><th>Gas cost</th><td>৳{{ number_format((float) $t['gas_cost'], 2) }}</td></tr>
    <tr><th>Transport cost</th><td>৳{{ number_format((float) $t['transport_cost'], 2) }}</td></tr>
    <tr><th>Total cost</th><td><b>৳{{ number_format((float) $t['total_cost'], 2) }}</b></td></tr>
    <tr><th>Average cost per kg (with transport)</th><td>৳{{ number_format((float) $t['avg_cost_per_kg'], 2) }}</td></tr>
    <tr><th>Approved / still pending</th><td>৳{{ number_format((float) $t['approved_cost'], 2) }} / ৳{{ number_format((float) $t['pending_cost'], 2) }}</td></tr>
    <tr><th>Bill photo attached / missing</th><td>{{ $t['with_invoice'] }} / <b>{{ $t['without_invoice'] }}</b></td></tr>
    <tr><th>All gas ever bought</th><td>{{ number_format((float) $ledger['overall']['quantity_kg'], 2) }} kg, ৳{{ number_format((float) $ledger['overall']['total_cost'], 2) }} in {{ $ledger['overall']['count'] }} purchases</td></tr>
</table>

@if (count($ledger['by_month']))
    <h2>By month</h2>
    <table>
        <tr><th>Month</th><th class="num">Purchases</th><th class="num">Kg</th><th class="num">Cost</th></tr>
        @foreach ($ledger['by_month'] as $m)
            <tr>
                <td>{{ date('M Y', strtotime($m['month'].'-01')) }}</td>
                <td class="num">{{ $m['count'] }}</td>
                <td class="num">{{ number_format((float) $m['quantity_kg'], 2) }}</td>
                <td class="num">{{ number_format((float) $m['total_cost'], 2) }}</td>
            </tr>
        @endforeach
    </table>
@endif

<h2>Ledger</h2>
<table class="ledger">
    <colgroup>
        <col style="width: 9%"><col style="width: 9%"><col style="width: 14%"><col style="width: 8%"><col style="width: 7%">
        <col style="width: 8%"><col style="width: 10%"><col style="width: 11%"><col style="width: 5%"><col style="width: 8%"><col style="width: 11%">
    </colgroup>
    <tr>
        <th>Ref</th><th>Date</th><th>Supplier</th>
        <th class="num">Kg</th><th class="num">Rate</th><th class="num">Transport</th><th class="num">Total</th>
        <th>Voucher</th><th>Bill</th><th class="num">Run. kg</th><th class="num">Run. total</th>
    </tr>
    @foreach ($ledger['rows'] as $r)
        <tr>
            <td>{{ $r['ref'] }}</td>
            <td>{{ date('d M y', strtotime($r['date'])) }}</td>
            <td>{{ $r['supplier_name'] }}</td>
            <td class="num">{{ number_format((float) $r['quantity_kg'], 2) }}</td>
            <td class="num">{{ number_format((float) $r['unit_price'], 2) }}</td>
            <td class="num">{{ number_format((float) $r['transport_cost'], 2) }}</td>
            <td class="num">{{ number_format((float) $r['total_cost'], 2) }}</td>
            <td>{{ $r['voucher_no'] ?: ($r['status'] === 'PENDING' ? 'pending' : '-') }}</td>
            <td>{{ $r['has_invoice'] ? 'yes' : 'NO' }}</td>
            <td class="num">{{ number_format((float) $r['running_kg'], 2) }}</td>
            <td class="num">{{ number_format((float) $r['running_cost'], 2) }}</td>
        </tr>
    @endforeach
    <tr class="total">
        <td colspan="3">Total</td>
        <td class="num">{{ number_format((float) $t['quantity_kg'], 2) }}</td>
        <td></td>
        <td class="num">{{ number_format((float) $t['transport_cost'], 2) }}</td>
        <td class="num">{{ number_format((float) $t['total_cost'], 2) }}</td>
        <td colspan="4"></td>
    </tr>
</table>
<div class="foot">Printed {{ now()->format('d M Y, h:i A') }}@if (count($items)) - voucher pages follow ({{ count($items) }})@endif</div>

@foreach ($items as $i => $item)
    <div class="newpage">
        <div class="head">
            <h1>{{ $building->name ?? 'Green Valley' }}</h1>
            <div>Gas Purchase Voucher - Audit Copy (B&amp;W)</div>
            <div class="muted">Voucher {{ $i + 1 }} of {{ count($items) }} - {{ $item['ref'] }}</div>
        </div>

        <table class="kv">
            <tr><th>Ref</th><td>{{ $item['ref'] }}</td></tr>
            <tr><th>Date</th><td>{{ $item['date'] }}</td></tr>
            <tr><th>Supplier</th><td>{{ $item['supplier'] }}</td></tr>
            <tr><th>Details</th><td>{{ $item['detail'] }}</td></tr>
            <tr><th>Total</th><td>৳{{ number_format($item['total'], 2) }}</td></tr>
            <tr><th>Voucher no / Status</th><td>{{ $item['voucher'] }} / {{ $item['status'] }}</td></tr>
        </table>

        {{-- this page's details table is taller than the invoice-audit one, so the photo box is smaller: 170 x 165 mm --}}
        @php($scale = min(1, 170 / max($item['image']['width_mm'], 1), 165 / max($item['image']['height_mm'], 1)))
        <div class="photo">
            <img src="{{ $item['image']['src'] }}" style="width: {{ round($item['image']['width_mm'] * $scale, 1) }}mm; height: {{ round($item['image']['height_mm'] * $scale, 1) }}mm;">
        </div>
        <div class="foot">Black &amp; white audit copy - printed {{ now()->format('d M Y, h:i A') }}</div>
    </div>
@endforeach

</body>
</html>
