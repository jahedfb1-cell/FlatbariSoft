<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    {!! \App\Services\PdfFontService::fontFaceCss() !!}

    @page { size: A4 portrait; margin: 7mm; }
    body { font-family: "Noto", "DejaVu Sans", sans-serif; font-size: 12px; color: #000; margin: 0; }
    /* four bills to an A4 page, two by two, with a cutting line between them */
    .page { width: 100%; }
    .page + .page { page-break-before: always; }
    table.grid { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.grid > tbody > tr > td.slot { width: 50%; height: 128mm; vertical-align: top; border: 1px dashed #777; padding: 5mm 6mm; }
    .head { text-align: center; border-bottom: 2px solid #000; padding-bottom: 6px; margin-bottom: 9px; }
    .head h1 { font-size: 17px; margin: 0 0 2px; }
    .head .t { font-size: 12px; font-weight: bold; letter-spacing: 1px; }
    .flat { font-size: 20px; font-weight: bold; }
    .who { color: #222; margin: 3px 0 10px; font-size: 12px; }
    table.lines { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
    table.lines th, table.lines td { border: 1px solid #000; padding: 6px 8px; text-align: left; }
    table.lines th { background: #eee; font-weight: normal; }
    table.lines td.num { text-align: right; white-space: nowrap; }
    table.lines tr.sub td { font-size: 11px; color: #222; border-top: 0; padding: 4px 8px; }
    table.lines tr.total th, table.lines tr.total td { font-weight: bold; background: #f2f2f2; font-size: 14px; }
    .due-box { border: 2px solid #000; padding: 8px 6px; text-align: center; margin-top: 8px; font-size: 13px; }
    .due-box b { font-size: 17px; }
    .note { margin-top: 9px; font-size: 10.5px; color: #222; }
</style>
</head>
<body>
@php($f = fn ($v) => number_format((float) $v, 2))
@php($has = fn ($v) => bccomp((string) $v, '0', 4) > 0)
@foreach ($slips->chunk(4) as $pageSlips)
    <div class="page">
        <table class="grid">
            @foreach ($pageSlips->chunk(2) as $row)
                <tr>
                    @foreach ($row as $s)
                        <td class="slot">
                            <div class="head">
                                <h1>{{ $building->name ?? 'Green Valley' }}</h1>
                                <div class="t">MONTHLY BILL &mdash; {{ $s['month_label'] }}</div>
                            </div>
                            @php($office = $s['is_office'] ?? false)
                            <div><span class="flat">{{ $office ? $s['flat_no'] : 'Flat '.$s['flat_no'] }}</span></div>
                            <div class="who">
                                @if ($office)
                                    Building's own gas use - not billed to any flat
                                @else
                                    Owner: <b>{{ $s['owner'] ?: '-' }}</b>@if ($s['tenant'])<br>Tenant: {{ $s['tenant'] }}@endif
                                @endif
                            </div>

                            <table class="lines">
                                @if ($has($s['previous_due']))
                                    <tr><th>Previous due</th><td class="num">{{ $f($s['previous_due']) }}</td></tr>
                                @endif
                                @if (! $office)
                                    <tr><th>Service charge</th><td class="num">{{ $f($s['service_charge']) }}</td></tr>
                                @endif
                                <tr><th>Gas bill</th><td class="num">{{ $f($s['gas_amount']) }}</td></tr>
                                @if ($s['gas_units'] !== null)
                                    <tr class="sub"><td colspan="2">Meter {{ rtrim(rtrim($f($s['gas_previous']), '0'), '.') }} &rarr; {{ rtrim(rtrim($f($s['gas_current']), '0'), '.') }}
                                        = <b>{{ rtrim(rtrim($f($s['gas_units']), '0'), '.') }} units</b>@if ($s['gas_rate']) &times; Tk {{ rtrim(rtrim($s['gas_rate'], '0'), '.') }}@endif</td></tr>
                                @elseif (! $has($s['gas_amount']))
                                    <tr class="sub"><td colspan="2">Gas reading not added yet</td></tr>
                                @endif
                                @if ($has($s['other_charges']))
                                    <tr><th>Other charges</th><td class="num">{{ $f($s['other_charges']) }}</td></tr>
                                @endif
                                @if ($has($s['late_fee']))
                                    <tr><th>Late fee</th><td class="num">{{ $f($s['late_fee']) }}</td></tr>
                                @endif
                                <tr class="total"><th>{{ $office ? 'Gas cost (Tk)' : 'Total payable (Tk)' }}</th><td class="num">{{ $f($s['total_payable']) }}</td></tr>
                                @if ($has($s['paid_amount']))
                                    <tr><th>Paid so far</th><td class="num">{{ $f($s['paid_amount']) }}</td></tr>
                                @endif
                            </table>

                            <div class="due-box">
                                @if ($office)
                                    <b>OFFICE USE</b> &mdash; paid from the gas fund, nothing to collect
                                @elseif ($has($s['due']))
                                    Balance due: <b>Tk {{ $f($s['due']) }}</b>
                                @else
                                    <b>PAID</b> &mdash; thank you
                                @endif
                            </div>
                            <div class="note">
                                @if ($office)
                                    Shown here with the flats so the month's gas adds up.
                                @else
                                @if ($s['due_date'] && $has($s['due']))Please pay by {{ $s['due_date'] }}. @endif
                                A money receipt is given for every payment.
                                @endif
                            </div>
                        </td>
                    @endforeach
                    @if ($row->count() < 2)
                        <td class="slot" style="border-style: none;"></td>
                    @endif
                </tr>
            @endforeach
        </table>
    </div>
@endforeach
</body>
</html>
