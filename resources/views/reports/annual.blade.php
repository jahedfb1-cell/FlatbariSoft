<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    {!! \App\Services\PdfFontService::fontFaceCss() !!}
    @page { margin: 14mm 12mm; }
    body { font-family: "Noto", "DejaVu Sans", sans-serif; font-size: 11px; color: #000; }
    h1 { font-size: 16px; margin: 0; text-align: center; }
    h2 { font-size: 13px; margin: 14px 0 3px; text-align: center; border-bottom: 2px solid #000; padding-bottom: 3px; }
    .sub { text-align: center; color: #333; margin-bottom: 6px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
    td { padding: 3px 6px; border-bottom: 1px solid #ccc; }
    td.n { text-align: right; white-space: nowrap; width: 110px; }
    td.i { padding-left: 22px; }
    tr.h td { font-weight: bold; background: #eee; border-top: 1px solid #000; }
    tr.t td { font-weight: bold; border-top: 1px solid #000; border-bottom: 2px solid #000; }
    .pb { page-break-before: always; }
    .note { font-size: 9px; color: #444; margin-top: 4px; }
    .sign { margin-top: 40px; display: table; width: 100%; }
    .sign div { display: table-cell; text-align: center; font-size: 10px; }
    .sign span { display: inline-block; border-top: 1px solid #000; padding: 2px 24px; }
</style>
</head>
<body>
@php($f = fn ($v) => number_format((float) $v, 2))
@php($cf = $r['cash_flow'])
@php($ie = $r['income_expenditure'])
@php($bs = $r['balance_sheet'])
<h1>{{ $building->name ?? 'Green Valley' }}</h1>
<div class="sub">বার্ষিক আর্থিক বিবরণী — অর্থবছর {{ $r['label'] }} ({{ $r['from'] }} থেকে {{ $r['to'] }})</div>

<h2>১. নগদ প্রবাহ বিবরণী (প্রাপ্তি ও প্রদান হিসাব)</h2>
<table>
    <tr class="h"><td>শুরুর স্থিতি (ক্যাশ ও ব্যাংক)</td><td class="n">{{ $f($cf['opening']) }}</td></tr>
    <tr class="h"><td colspan="2">প্রাপ্তি</td></tr>
    @foreach ($cf['receipts'] as $x)<tr><td class="i">{{ $x['label'] }}</td><td class="n">{{ $f($x['amount']) }}</td></tr>@endforeach
    <tr class="t"><td>মোট প্রাপ্তি</td><td class="n">{{ $f($cf['total_receipts']) }}</td></tr>
    <tr class="h"><td colspan="2">প্রদান</td></tr>
    @foreach ($cf['payments'] as $x)<tr><td class="i">{{ $x['label'] }}</td><td class="n">{{ $f($x['amount']) }}</td></tr>@endforeach
    <tr class="t"><td>মোট প্রদান</td><td class="n">{{ $f($cf['total_payments']) }}</td></tr>
    <tr class="t"><td>নিট নগদ প্রবাহ</td><td class="n">{{ $f($cf['net']) }}</td></tr>
    <tr class="t"><td>শেষ স্থিতি (ক্যাশ {{ $f($cf['closing_cash']) }} + ব্যাংক {{ $f($cf['closing_bank']) }})</td><td class="n">{{ $f($cf['closing']) }}</td></tr>
</table>
<div class="note">ক্যাশ ও ব্যাংকের মধ্যে স্থানান্তর এবং তহবিল থেকে তহবিলে ধার মোট স্থিতিতে প্রভাব ফেলে না, তাই প্রাপ্তি-প্রদানে ধরা হয়নি।</div>

<h2 class="pb">২. আয়-ব্যয় বিবরণী (লাভ-ক্ষতি)</h2>
<div class="sub">বিল করা আয় ও দায়সহ ব্যয় (বকেয়াসহ হিসাব)</div>
<table>
    <tr class="h"><td colspan="2">আয়</td></tr>
    @foreach ($ie['income'] as $x)<tr><td class="i">{{ $x['label'] }}</td><td class="n">{{ $f($x['amount']) }}</td></tr>@endforeach
    <tr class="t"><td>মোট আয়</td><td class="n">{{ $f($ie['total_income']) }}</td></tr>
    <tr class="h"><td colspan="2">ব্যয়</td></tr>
    @foreach ($ie['expenditure'] as $x)<tr><td class="i">{{ $x['label'] }}</td><td class="n">{{ $f($x['amount']) }}</td></tr>@endforeach
    <tr class="t"><td>মোট ব্যয়</td><td class="n">{{ $f($ie['total_expenditure']) }}</td></tr>
    <tr class="t"><td>উদ্বৃত্ত / (ঘাটতি)</td><td class="n">{{ $f($ie['surplus']) }}</td></tr>
</table>
@if (count($ie['capital_spending']) > 0 || count($ie['special_collections']) > 0)
<table>
    <tr class="h"><td colspan="2">মূলধনী লেনদেন (প্রকল্প / কাজ — আয়-ব্যয়ের বাইরে)</td></tr>
    @foreach ($ie['special_collections'] as $x)<tr><td class="i">{{ $x['label'] }}</td><td class="n">{{ $f($x['amount']) }}</td></tr>@endforeach
    @foreach ($ie['capital_spending'] as $x)<tr><td class="i">খরচ: {{ $x['label'] }}</td><td class="n">{{ $f($x['amount']) }}</td></tr>@endforeach
</table>
@endif
<div class="note">স্মারক: এ বছরে বিল করা {{ $f($ie['memo']['billed']) }} · আদায় হয়েছে {{ $f($ie['memo']['collected']) }}@if ($ie['memo']['collection_percent'] !== null) ({{ $ie['memo']['collection_percent'] }}%)@endif</div>

<h2 class="pb">৩. আর্থিক অবস্থার বিবরণী (স্থিতিপত্র) — {{ $bs['as_of'] }} তারিখে</h2>
<table>
    <tr class="h"><td colspan="2">সম্পদ</td></tr>
    @foreach ($bs['assets'] as $x)<tr><td class="i">{{ $x['label'] }}</td><td class="n">{{ $f($x['amount']) }}</td></tr>@endforeach
    <tr class="t"><td>মোট সম্পদ</td><td class="n">{{ $f($bs['total_assets']) }}</td></tr>
    <tr class="h"><td colspan="2">দায়</td></tr>
    @foreach ($bs['liabilities'] as $x)<tr><td class="i">{{ $x['label'] }}</td><td class="n">{{ $f($x['amount']) }}</td></tr>@endforeach
    <tr class="t"><td>মোট দায়</td><td class="n">{{ $f($bs['total_liabilities']) }}</td></tr>
    <tr class="h"><td colspan="2">তহবিল</td></tr>
    @foreach ($bs['funds'] as $x)<tr><td class="i">{{ $x['label'] }}</td><td class="n">{{ $f($x['amount']) }}</td></tr>@endforeach
    <tr><td class="i">{{ $bs['accrued']['label'] }}</td><td class="n">{{ $f($bs['accrued']['amount']) }}</td></tr>
    <tr class="t"><td>মোট তহবিল</td><td class="n">{{ $f($bs['total_funds']) }}</td></tr>
    <tr class="t"><td>মোট দায় ও তহবিল</td><td class="n">{{ $f($bs['total_liabilities_and_funds']) }}</td></tr>
</table>
<div class="note">প্রতিটি তহবিলের অঙ্ক সেই তহবিলের ক্যাশ ও ব্যাংকের টাকা। ফ্ল্যাটদের কাছে পাওনা ও স্টাফের অগ্রিম (দেনা বাদে) আলাদা সারিতে।</div>

<div class="sign"><div><span>সভাপতি</span></div><div><span>সম্পাদক</span></div><div><span>কোষাধ্যক্ষ</span></div></div>
<div class="note" style="text-align:right">তৈরি: {{ now('Asia/Dhaka')->format('d/m/Y h:i A') }}</div>
</body>
</html>
