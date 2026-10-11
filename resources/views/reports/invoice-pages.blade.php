<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    {!! \App\Services\PdfFontService::fontFaceCss() !!}

    /* Strictly black on white / greys - this is printed on a mono printer for audits. */
    @page { size: A4; margin: 20mm 15mm; }
    body { font-family: "Noto", "DejaVu Sans", sans-serif; font-size: 11px; color: #000; }
    h1 { font-size: 15px; margin: 0 0 2px; }
    h2 { font-size: 13px; margin: 14px 0 6px; }
    .head { text-align: center; border-bottom: 2px solid #000; padding-bottom: 6px; margin-bottom: 8px; }
    .muted { color: #444; font-size: 10px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    th, td { border: 1px solid #000; padding: 4px 7px; text-align: left; }
    th { background: #e6e6e6; width: 24%; }
    table.list th { width: auto; }
    td.num { text-align: right; }
    .photo { text-align: center; margin-top: 6px; }
    .photo img { border: 1px solid #000; }
    .foot { margin-top: 6px; font-size: 9px; color: #444; text-align: right; }
    .newpage { page-break-before: always; }
</style>
</head>
<body>

@if ($cover)
    <div class="head">
        <h1>{{ $building->name ?? 'Green Valley' }}</h1>
        <div>{{ $title }}</div>
        <div class="muted">Period: {{ $cover['from'] }} to {{ $cover['to'] }}</div>
    </div>

    <table>
        <tr><th>Purchases in period</th><td>{{ $cover['purchases'] }}</td></tr>
        <tr><th>With invoice photo</th><td>{{ $cover['with_invoice'] }}</td></tr>
        <tr><th>WITHOUT invoice photo</th><td><b>{{ $cover['without_invoice'] }}</b></td></tr>
        <tr><th>Total value of purchases</th><td>৳{{ number_format($cover['total'], 2) }}</td></tr>
    </table>

    @if (count($cover['missing']))
        <h2>Purchases with no invoice photo attached</h2>
        <table class="list">
            <tr><th>Date</th><th>Type</th><th>Supplier</th><th>Details</th><th class="num">Total</th><th>Status</th></tr>
            @foreach ($cover['missing'] as $m)
                <tr>
                    <td>{{ $m['date'] }}</td>
                    <td>{{ $m['type_label'] }}</td>
                    <td>{{ $m['supplier'] }}</td>
                    <td>{{ $m['detail'] }}</td>
                    <td class="num">৳{{ number_format($m['total'], 2) }}</td>
                    <td>{{ $m['status'] }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <div class="foot">Printed {{ now()->format('d M Y, h:i A') }} - invoice pages follow ({{ $cover['with_invoice'] }})</div>
@endif

@foreach ($items as $i => $item)
    <div class="{{ ($cover || $i > 0) ? 'newpage' : '' }}">
        <div class="head">
            <h1>{{ $building->name ?? 'Green Valley' }}</h1>
            <div>{{ $cover ? 'Purchase Invoice - Audit Copy (B&W)' : $title }}</div>
            @if ($cover)<div class="muted">Invoice {{ $i + 1 }} of {{ count($items) }}</div>@endif
        </div>

        <table>
            <tr><th>Type</th><td>{{ $item['type_label'] }}</td><th>Date</th><td>{{ $item['date'] }}</td></tr>
            <tr><th>Supplier</th><td>{{ $item['supplier'] }}</td><th>Total</th><td>৳{{ number_format($item['total'], 2) }}</td></tr>
            <tr><th>Details</th><td>{{ $item['detail'] }}</td><th>Voucher / Status</th><td>{{ $item['voucher'] }} / {{ $item['status'] }}</td></tr>
        </table>

        <div class="photo">
            <img src="{{ $item['image']['src'] }}" style="width: {{ $item['image']['width_mm'] }}mm; height: {{ $item['image']['height_mm'] }}mm;">
        </div>

        <div class="foot">Black &amp; white audit copy - printed {{ now()->format('d M Y, h:i A') }}</div>
    </div>
@endforeach

</body>
</html>
