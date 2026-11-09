<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    {!! \App\Services\PdfFontService::fontFaceCss() !!}

    @page { size: A4 landscape; margin: 12mm 12mm; }
    body { font-family: "Noto", "DejaVu Sans", sans-serif; font-size: 10px; color: #000; }
    .head { text-align: center; margin-bottom: 8px; }
    .head .org { font-size: 17px; font-weight: bold; letter-spacing: 1px; }
    .head .title { font-size: 14px; font-weight: bold; margin-top: 2px; }
    .head .sub { font-size: 10.5px; margin-top: 2px; }
    table.t { width: 100%; border-collapse: collapse; }
    table.t th, table.t td { border: 1px solid #000; padding: 3px 5px; vertical-align: top; }
    table.t th { background: #e6e6e6; text-align: center; }
    table.t th.side { font-size: 11px; letter-spacing: 1px; }
    td.num { text-align: right; white-space: nowrap; }
    td.date { white-space: nowrap; text-align: center; }
    tr.sub td { background: #f4f4f4; }
    tr.total td { font-weight: bold; background: #e9e9e9; border-top: 2px solid #000; }
    .note { margin-top: 6px; font-size: 9px; }
    .sign { margin-top: 34px; display: table; width: 100%; }
    .sign div { display: table-cell; width: 33%; text-align: center; font-size: 9.5px; }
    .sign span { display: inline-block; border-top: 1px solid #000; padding: 2px 16px; }
    .foot { margin-top: 8px; font-size: 8px; color: #444; text-align: right; }
</style>
</head>
<body>
@php
    // Indian digit grouping (1,45,550.00), the way the building's accounts are written
    $f = function ($v) {
        $n = number_format(abs((float) $v), 2, '.', '');
        [$int, $dec] = explode('.', $n);
        if (strlen($int) > 3) {
            $last3 = substr($int, -3);
            $rest = substr($int, 0, -3);
            $int = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest).','.$last3;
        }

        return ((float) $v < 0 ? '-' : '').$int.'.'.$dec;
    };
    $d = fn ($iso) => date('d.m.Y', strtotime($iso));
    $monthName = date('F Y', strtotime($r['from']));
    $first = $d($r['from']);
    $last = $d($r['to']);

    // deposit side: the gas bills received from the flats are one line for the month; everything else one line each
    $deposit = [];
    $billTotal = '0';
    foreach ($r['receipts'] as $x) {
        if ($x['head_code'] === 'GAS_BILL') {
            $billTotal = bcadd($billTotal, $x['amount'], 4);
        } else {
            $deposit[] = ['date' => $d($x['date']), 'text' => $x['particulars'], 'amount' => $x['amount']];
        }
    }
    if (bccomp($billTotal, '0', 4) > 0) {
        array_unshift($deposit, ['date' => $last, 'text' => 'Gas bill received from flat owners', 'amount' => $billTotal]);
    }

    // expenditure side: a gas purchase reads "2 Cylinders LPG Gas x Tk 7,700 (each cylinder 45 kg)"; anything else as it was booked
    $spend = [];
    foreach ($r['payments'] as $x) {
        $g = $x['gas'] ?? null;
        if ($g && $g['cylinders']) {
            $text = sprintf('%d Cylinder%s LPG Gas x Tk %s (each cylinder %s kg)', $g['cylinders'], (int) $g['cylinders'] === 1 ? '' : 's', $f($g['price_per_cylinder']), rtrim(rtrim(number_format((float) $g['cylinder_kg'], 2, '.', ''), '0'), '.'));
            if ((float) $g['transport'] > 0) {
                $text .= ' + transport Tk '.$f($g['transport']);
            }
        } elseif ($g) {
            $text = 'LPG Gas '.rtrim(rtrim(number_format((float) $g['kg'], 2, '.', ''), '0'), '.').' kg'.((float) $g['transport'] > 0 ? ' + transport Tk '.$f($g['transport']) : '');
        } else {
            $text = $x['particulars'];
        }
        $spend[] = ['date' => $d($x['date']), 'text' => $text, 'amount' => $x['amount']];
    }

    $openingTotal = bcadd($r['opening']['cash'], $r['opening']['bank'], 4);
    $closingTotal = bcadd($r['closing']['cash'], $r['closing']['bank'], 4);
    $rows = max(count($deposit), count($spend));
@endphp

<div class="head">
    <div class="org">{{ strtoupper($building->name ?? 'Green Valley') }}</div>
    <div class="title">Accounts of LPG Purchase</div>
    <div class="sub">Statement of Deposit and Expenditure for the Month of {{ $monthName }}</div>
</div>

<table class="t">
    <tr><th class="side" colspan="3">DEPOSIT</th><th class="side" colspan="3">EXPENDITURE</th></tr>
    <tr><th style="width:9%">Date</th><th>Particulars</th><th style="width:12%">Taka</th><th style="width:9%">Date</th><th>Particulars</th><th style="width:12%">Taka</th></tr>
    <tr class="sub">
        <td class="date">{{ $first }}</td><td>Balance brought forward (cash in hand {{ $f($r['opening']['cash']) }}, at bank {{ $f($r['opening']['bank']) }})</td><td class="num">{{ $f($openingTotal) }}</td>
        <td colspan="3"></td>
    </tr>
    @for ($i = 0; $i < $rows; $i++)
        <tr>
            @if (isset($deposit[$i]))
                <td class="date">{{ $deposit[$i]['date'] }}</td><td>{{ $deposit[$i]['text'] }}</td><td class="num">{{ $f($deposit[$i]['amount']) }}</td>
            @else
                <td></td><td></td><td></td>
            @endif
            @if (isset($spend[$i]))
                <td class="date">{{ $spend[$i]['date'] }}</td><td>{{ $spend[$i]['text'] }}</td><td class="num">{{ $f($spend[$i]['amount']) }}</td>
            @else
                <td></td><td></td><td></td>
            @endif
        </tr>
    @endfor
    <tr class="sub">
        <td colspan="3"></td>
        <td class="date">{{ $last }}</td><td>Balance carried forward (cash in hand {{ $f($r['closing']['cash']) }}, at bank {{ $f($r['closing']['bank']) }})</td><td class="num">{{ $f($closingTotal) }}</td>
    </tr>
    <tr class="total">
        <td class="date">{{ $last }}</td><td>Total</td><td class="num">{{ $f($r['left_total']) }}</td>
        <td class="date">{{ $last }}</td><td>Total</td><td class="num">{{ $f($r['right_total']) }}</td>
    </tr>
</table>

<div class="note">
    Total paid for LPG gas this month: <b>Tk {{ $f($r['totals']['payments']) }}</b>
    @foreach ($r['loans'] as $l)
        &nbsp;|&nbsp; {{ $l['borrower_name_en'] }} owes {{ $l['lender_name_en'] }} Tk {{ $f($l['owed']) }}
    @endforeach
</div>

<div class="sign"><div><span>Prepared by</span></div><div><span>Treasurer</span></div><div><span>Secretary / President</span></div></div>
<div class="foot">Printed {{ now()->format('d M Y, h:i A') }} - generated from the gas fund's cash and bank ledgers</div>
</body>
</html>
