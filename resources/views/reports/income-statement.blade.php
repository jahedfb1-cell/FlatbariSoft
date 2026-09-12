<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
@include('reports._style')
</head>
<body>
@include('reports._letterhead', ['reportTitle' => 'Income Statement — FY ' . $report['fiscal_year']])

<table>
    <tbody>
        <tr><td>Total Income</td><td class="num">৳{{ number_format($report['total_income'], 2) }}</td></tr>
        <tr><td>Total Expense</td><td class="num">৳{{ number_format($report['total_expense'], 2) }}</td></tr>
        <tr class="total">
            <td>Net Surplus / (Deficit)</td>
            <td class="num">৳{{ number_format($report['net_surplus'], 2) }}</td>
        </tr>
    </tbody>
</table>

<div class="footer">Green Valley BMS — Income Statement</div>
</body>
</html>
