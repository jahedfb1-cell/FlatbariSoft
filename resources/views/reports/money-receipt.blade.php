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
    <div class="title">MONEY RECEIPT</div>
</div>

<table>
    <tr><th>Receipt no</th><td><b>{{ $payment->receipt_no }}</b></td><th>Date</th><td>{{ $payment->payment_date->format('d M Y') }}</td></tr>
    <tr>
        <th>Received from</th><td>{{ $owner ?: '-' }}</td>
        <th>Flat</th><td><b>{{ $payment->flat->flat_no }}</b></td>
    </tr>
    <tr>
        <th>Towards</th>
        <td>{{ $payment->bill ? 'Bill of '.date('F Y', mktime(0, 0, 0, $payment->bill->billingPeriod->month, 1, $payment->bill->billingPeriod->year)) : 'Payment' }}</td>
        <th>Paid by</th><td>{{ $payment->method }}{{ $payment->reference_no ? ' / '.$payment->reference_no : '' }}</td>
    </tr>
</table>

<table>
    <tr><th>Service charge @if (bccomp((string) ($payment->bill->previous_due ?? 0), '0', 4) > 0)(incl. previous due)@endif</th><td class="num">{{ $f($alloc['service']) }}</td></tr>
    @if (bccomp($alloc['gas'], '0', 4) > 0)
        <tr><th>Gas bill</th><td class="num">{{ $f($alloc['gas']) }}</td></tr>
    @endif
    <tr class="total"><th>Total received (Taka)</th><td class="num">{{ $f($payment->amount) }}</td></tr>
</table>

@if ($payment->bill)
    <table>
        <tr><th>Bill total</th><td class="num">{{ $f($payment->bill->total_payable) }}</td><th>Paid so far</th><td class="num">{{ $f($payment->bill->paid_amount) }}</td></tr>
        <tr><th>Balance due</th><td class="num"><b>{{ $f(max(0, (float) bcsub((string) $payment->bill->total_payable, (string) $payment->bill->paid_amount, 4))) }}</b></td><th>Status</th><td>{{ $payment->bill->status }}</td></tr>
    </table>
@endif

<div class="sign"><div><span>Received by</span></div><div><span>Authorised signature</span></div></div>
<div class="foot">Printed {{ now()->format('d M Y, h:i A') }}</div>
</body>
</html>
