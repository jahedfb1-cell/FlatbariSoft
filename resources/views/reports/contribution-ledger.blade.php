<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    {!! \App\Services\PdfFontService::fontFaceCss() !!}

    @page { size: A4; margin: 18mm 14mm; }
    body { font-family: "Noto", "DejaVu Sans", sans-serif; font-size: 10px; color: #000; }
    h1 { font-size: 15px; margin: 0 0 2px; }
    .head { text-align: center; border-bottom: 2px solid #000; padding-bottom: 6px; margin-bottom: 10px; }
    .muted { color: #444; font-size: 9px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    th, td { border: 1px solid #000; padding: 3px 6px; text-align: left; }
    th { background: #e6e6e6; }
    td.num, th.num { text-align: right; }
    table.kv th { width: 28%; }
    tr.total td { font-weight: bold; background: #f0f0f0; }
    tr.rev td { color: #555; font-style: italic; }
    .sign { margin-top: 38px; display: table; width: 100%; }
    .sign div { display: table-cell; width: 50%; text-align: center; font-size: 9px; }
    .sign span { display: inline-block; border-top: 1px solid #000; padding: 2px 18px; }
    .foot { margin-top: 8px; font-size: 8.5px; color: #444; text-align: right; }
    .newpage { page-break-before: always; }
</style>
</head>
<body>
@foreach ($ledgers as $i => $l)
    <div class="{{ $i > 0 ? 'newpage' : '' }}">
        <div class="head">
            <h1>{{ $building->name ?? 'Green Valley' }}</h1>
            <div>Construction Cost - Owner Ledger</div>
        </div>

        <table class="kv">
            <tr><th>Flat</th><td><b>{{ $l['flat_no'] }}</b></td></tr>
            <tr><th>Owner</th><td>{{ $l['owner'] ?: '-' }}</td></tr>
            <tr><th>Construction cost target</th><td>{{ number_format((float) $l['target'], 2) }}</td></tr>
            <tr><th>Collected so far</th><td>{{ number_format((float) $l['collected'], 2) }}</td></tr>
            <tr>
                <th>{{ bccomp($l['balance'], '0', 4) >= 0 ? 'Still due' : 'Paid in excess' }}</th>
                <td><b>{{ number_format(abs((float) $l['balance']), 2) }}</b></td>
            </tr>
        </table>

        <table>
            <tr>
                <th>Date</th><th>Receipt</th><th>Head</th><th>Method / ref</th>
                <th class="num">Amount</th><th class="num">Balance after</th>
            </tr>
            <tr>
                <td colspan="5">Target</td>
                <td class="num">{{ number_format((float) $l['target'], 2) }}</td>
            </tr>
            @forelse ($l['entries'] as $e)
                <tr class="{{ $e['is_reversal'] || $e['is_reversed'] ? 'rev' : '' }}">
                    <td>{{ date('d M Y', strtotime($e['date'])) }}</td>
                    <td>{{ $e['receipt_no'] }}</td>
                    <td>{{ $e['head_name_en'] }}@if (! $e['counts_toward_target']) (not counted)@endif @if ($e['is_reversal']) - reversal @elseif ($e['is_reversed']) - reversed @endif</td>
                    <td>{{ $e['method'] === 'OPENING' ? 'collected earlier' : $e['method'] }}{{ $e['reference_no'] ? ' / '.$e['reference_no'] : '' }}</td>
                    <td class="num">{{ number_format((float) $e['amount'], 2) }}</td>
                    <td class="num">{{ number_format((float) $e['balance_after'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="6">No receipts yet.</td></tr>
            @endforelse
            <tr class="total">
                <td colspan="4">Collected / {{ bccomp($l['balance'], '0', 4) >= 0 ? 'still due' : 'excess' }}</td>
                <td class="num">{{ number_format((float) $l['collected'], 2) }}</td>
                <td class="num">{{ number_format(abs((float) $l['balance']), 2) }}</td>
            </tr>
        </table>

        <div class="sign"><div><span>Building manager</span></div><div><span>Treasurer</span></div></div>
        <div class="foot">Printed {{ now()->format('d M Y, h:i A') }}</div>
    </div>
@endforeach
</body>
</html>
