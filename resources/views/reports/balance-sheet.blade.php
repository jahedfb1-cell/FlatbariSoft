<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
@include('reports._style')
</head>
<body>
@include('reports._letterhead', ['reportTitle' => 'Balance Sheet'])

<table>
    <tbody>
        <tr><td>Assets</td><td class="num">৳{{ number_format($report['assets'], 2) }}</td></tr>
        <tr><td>Liabilities</td><td class="num">৳{{ number_format($report['liabilities'], 2) }}</td></tr>
        <tr><td>Funds</td><td class="num">৳{{ number_format($report['funds'], 2) }}</td></tr>
        <tr><td>Current (Unclosed) Net Income</td><td class="num">৳{{ number_format($report['current_net_income'], 2) }}</td></tr>
        <tr class="total">
            <td>Liabilities + Funds + Net Income</td>
            <td class="num">৳{{ number_format($report['liabilities'] + $report['funds'] + $report['current_net_income'], 2) }}</td>
        </tr>
    </tbody>
</table>

<p>
    Status:
    <span class="{{ $report['balanced'] ? 'status-ok' : 'status-bad' }}">
        {{ $report['balanced'] ? 'Balanced' : 'NOT Balanced' }}
    </span>
</p>

<div class="footer">Green Valley BMS — Balance Sheet</div>
</body>
</html>
