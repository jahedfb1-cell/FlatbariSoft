@php
    $f = fn ($v) => number_format((float) $v, 2);
    $monthName = date('F Y', strtotime($r['from']));
    $rows = max(count($r['receipts']), count($r['payments']));
    $medium = fn ($m) => $m === 'CASH' ? 'cash' : 'bank';
@endphp

<div class="head">
    <h1>{{ $building->name ?? 'Green Valley' }}</h1>
    <div><b>{{ $r['fund']['name_en'] }}</b> — Statement of Receipts and Payments</div>
    <div class="muted">Month of {{ $monthName }}</div>
</div>

<h2>Summary</h2>
<table>
    <tr><th colspan="2">RECEIPTS</th><th colspan="2">PAYMENTS</th></tr>
    <tr>
        <td colspan="2" style="padding:0; border:0">
            <table style="margin:0">
                <tr class="sub"><td>Opening balance — cash in hand</td><td class="num">{{ $f($r['opening']['cash']) }}</td></tr>
                <tr class="sub"><td>Opening balance — cash at bank</td><td class="num">{{ $f($r['opening']['bank']) }}</td></tr>
                @foreach ($r['receipts_by_head'] as $h)
                    <tr><td>{{ $h['name_en'] }}</td><td class="num">{{ $f($h['amount']) }}</td></tr>
                @endforeach
            </table>
        </td>
        <td colspan="2" style="padding:0; border:0">
            <table style="margin:0">
                @foreach ($r['payments_by_head'] as $h)
                    <tr><td>{{ $h['name_en'] }}</td><td class="num">{{ $f($h['amount']) }}</td></tr>
                @endforeach
                <tr class="sub"><td>Closing balance — cash in hand</td><td class="num">{{ $f($r['closing']['cash']) }}</td></tr>
                <tr class="sub"><td>Closing balance — cash at bank</td><td class="num">{{ $f($r['closing']['bank']) }}</td></tr>
            </table>
        </td>
    </tr>
    <tr class="total">
        <td>Grand total</td><td class="num">{{ $f($r['left_total']) }}</td>
        <td>Grand total</td><td class="num">{{ $f($r['right_total']) }}</td>
    </tr>
</table>

<h2>Details</h2>
<table>
    <tr><th colspan="3">RECEIPTS</th><th colspan="3">PAYMENTS</th></tr>
    <tr><th style="width:9%">Date</th><th>Particulars</th><th style="width:11%">Taka</th><th style="width:9%">Date</th><th>Particulars</th><th style="width:11%">Taka</th></tr>
    <tr class="sub">
        <td>{{ date('d.m.Y', strtotime($r['from'])) }}</td><td>Opening balance: cash in hand (B/F)</td><td class="num">{{ $f($r['opening']['cash']) }}</td>
        <td colspan="3"></td>
    </tr>
    <tr class="sub">
        <td>{{ date('d.m.Y', strtotime($r['from'])) }}</td><td>Opening balance: cash at bank (B/F)</td><td class="num">{{ $f($r['opening']['bank']) }}</td>
        <td colspan="3"></td>
    </tr>
    @for ($i = 0; $i < $rows; $i++)
        <tr>
            @if (isset($r['receipts'][$i]))
                <td>{{ date('d.m.Y', strtotime($r['receipts'][$i]['date'])) }}</td>
                <td>{{ $r['receipts'][$i]['particulars'] }}</td>
                <td class="num">{{ $f($r['receipts'][$i]['amount']) }}</td>
            @else
                <td></td><td></td><td></td>
            @endif
            @if (isset($r['payments'][$i]))
                <td>{{ date('d.m.Y', strtotime($r['payments'][$i]['date'])) }}</td>
                <td>{{ $r['payments'][$i]['particulars'] }}</td>
                <td class="num">{{ $f($r['payments'][$i]['amount']) }}</td>
            @else
                <td></td><td></td><td></td>
            @endif
        </tr>
    @endfor
    <tr class="total">
        <td></td><td>Total receipts</td><td class="num">{{ $f($r['totals']['receipts']) }}</td>
        <td></td><td>Total payments</td><td class="num">{{ $f($r['totals']['payments']) }}</td>
    </tr>
    <tr class="sub">
        <td colspan="3"></td>
        <td>{{ date('d.m.Y', strtotime($r['to'])) }}</td><td>Closing balance: cash at bank</td><td class="num">{{ $f($r['closing']['bank']) }}</td>
    </tr>
    <tr class="sub">
        <td colspan="3"></td>
        <td>{{ date('d.m.Y', strtotime($r['to'])) }}</td><td>Closing balance: cash in hand</td><td class="num">{{ $f($r['closing']['cash']) }}</td>
    </tr>
    <tr class="total">
        <td>{{ date('d.m.Y', strtotime($r['to'])) }}</td><td>Grand total</td><td class="num">{{ $f($r['left_total']) }}</td>
        <td>{{ date('d.m.Y', strtotime($r['to'])) }}</td><td>Grand total</td><td class="num">{{ $f($r['right_total']) }}</td>
    </tr>
</table>

@php($b = $r['bank_note'])
<h2>Note — bank account</h2>
<table class="kv" style="width:62%">
    <tr><th>Cash at bank at the start of the month</th><td class="num">{{ $f($b['opening']) }}</td></tr>
    <tr><th>Cash deposited in bank</th><td class="num">{{ $f($b['cash_deposited']) }}</td></tr>
    <tr><th>Other receipts at bank</th><td class="num">{{ $f($b['other_receipts']) }}</td></tr>
    <tr><th>Profit received from bank</th><td class="num">{{ $f($b['profit']) }}</td></tr>
    <tr><th>Cash withdrawn from bank</th><td class="num">({{ $f($b['withdrawn']) }})</td></tr>
    <tr><th>Payments made from bank</th><td class="num">({{ $f($b['payments']) }})</td></tr>
    <tr class="total"><th>Balance at bank at the end of the month</th><td class="num">{{ $f($b['closing']) }}</td></tr>
</table>

@if (count($r['loans']))
    <h2>Loans between funds still outstanding</h2>
    <table class="kv" style="width:62%">
        @foreach ($r['loans'] as $l)
            <tr><th>{{ $l['borrower_name_en'] }} owes {{ $l['lender_name_en'] }}</th><td class="num">{{ $f($l['owed']) }}</td></tr>
        @endforeach
    </table>
@endif

