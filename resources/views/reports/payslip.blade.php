<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    {!! \App\Services\PdfFontService::fontFaceCss() !!}
    @page { margin: 12mm; }
    body { font-family: "Noto", "DejaVu Sans", sans-serif; font-size: 11px; color: #000; }
    h1 { font-size: 16px; margin: 0; text-align: center; }
    .sub { text-align: center; font-weight: bold; letter-spacing: 1px; border-bottom: 2px solid #000; padding-bottom: 6px; margin-bottom: 10px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    th, td { border: 1px solid #000; padding: 4px 7px; text-align: left; }
    th { background: #eee; width: 45%; }
    td.num { text-align: right; }
    tr.total th, tr.total td { font-weight: bold; background: #f2f2f2; font-size: 12px; }
    .sign { margin-top: 30px; display: table; width: 100%; }
    .sign div { display: table-cell; width: 50%; text-align: center; font-size: 10px; }
    .sign span { display: inline-block; border-top: 1px solid #000; padding: 2px 20px; }
</style>
</head>
<body>
@php($f = fn ($v) => number_format((float) $v, 2))
@php($months = ['', 'জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'])
<h1>{{ $building->name ?? 'Green Valley' }}</h1>
<div class="sub">বেতন স্লিপ — {{ $months[$p->month] }} {{ $p->year }}</div>
<table>
    <tr><th>নাম</th><td>{{ $p->staff->name }}</td></tr>
    <tr><th>পদবি</th><td>{{ $p->staff->designation }}</td></tr>
    <tr><th>মোবাইল</th><td>{{ $p->staff->mobile }}</td></tr>
</table>
<table>
    <tr><th>মূল বেতন</th><td class="num">{{ $f($p->basic) }}</td></tr>
    <tr><th>ওভারটাইম</th><td class="num">{{ $f($p->overtime) }}</td></tr>
    <tr><th>বোনাস</th><td class="num">{{ $f($p->bonus) }}</td></tr>
    <tr><th>অগ্রিম কর্তন</th><td class="num">- {{ $f($p->advance_deduction) }}</td></tr>
    <tr class="total"><th>নিট বেতন</th><td class="num">{{ $f($p->net_amount) }}</td></tr>
</table>
<table>
    <tr><th>অবস্থা</th><td>{{ $p->expense?->status === 'APPROVED' ? 'পরিশোধিত' : ($p->expense_id ? 'অনুমোদনের অপেক্ষায়' : 'এখনো দেওয়া হয়নি') }}</td></tr>
    @if ($p->paid_date)<tr><th>তারিখ / মাধ্যম</th><td>{{ $p->paid_date->format('d M Y') }} / {{ $p->treasury_medium === 'CASH' ? 'নগদ' : 'ব্যাংক' }}</td></tr>@endif
    @if ($p->expense?->voucher_no)<tr><th>ভাউচার নং</th><td>{{ $p->expense->voucher_no }}</td></tr>@endif
</table>
<div class="sign"><div><span>প্রাপকের স্বাক্ষর</span></div><div><span>কর্তৃপক্ষের স্বাক্ষর</span></div></div>
</body>
</html>
