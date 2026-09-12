<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
@include('reports._style')
</head>
<body>
@include('reports._letterhead', ['reportTitle' => 'Trial Balance'])

<table>
    <thead>
        <tr>
            <th>Account</th>
            <th class="num">Debit</th>
            <th class="num">Credit</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($report['lines'] as $line)
        <tr>
            <td>{{ $line['account_code'] }} — {{ $line['account_name_en'] ?? $line['account_name'] }}</td>
            <td class="num">{{ $line['debit'] > 0 ? '৳' . number_format($line['debit'], 2) : '' }}</td>
            <td class="num">{{ $line['credit'] > 0 ? '৳' . number_format($line['credit'], 2) : '' }}</td>
        </tr>
        @endforeach
        <tr class="total">
            <td>Total</td>
            <td class="num">৳{{ number_format($report['total_debit'], 2) }}</td>
            <td class="num">৳{{ number_format($report['total_credit'], 2) }}</td>
        </tr>
    </tbody>
</table>

<p>
    Status:
    <span class="{{ $report['balanced'] ? 'status-ok' : 'status-bad' }}">
        {{ $report['balanced'] ? 'Balanced' : 'NOT Balanced' }}
    </span>
</p>

<div class="footer">Green Valley BMS — Trial Balance</div>
</body>
</html>
