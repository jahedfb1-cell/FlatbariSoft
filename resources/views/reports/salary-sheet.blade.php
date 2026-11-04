<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    {!! \App\Services\PdfFontService::fontFaceCss() !!}
    @page { margin: 10mm; }
    body { font-family: "Noto", "DejaVu Sans", sans-serif; font-size: 10px; color: #000; }
    h1 { font-size: 15px; margin: 0; text-align: center; }
    .sub { text-align: center; margin-bottom: 8px; font-weight: bold; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #000; padding: 3px 5px; }
    th { background: #eee; }
    td.num { text-align: right; }
    tr.total td { font-weight: bold; background: #f2f2f2; }
</style>
</head>
<body>
@php($f = fn ($v) => number_format((float) $v, 2))
@php($months = ['', 'জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'])
@php($state = ['NOT_GENERATED' => 'শিট হয়নি', 'UNPAID' => 'বাকি', 'AWAITING_APPROVAL' => 'অনুমোদনের অপেক্ষায়', 'PAID' => 'পরিশোধিত'])
<h1>{{ $building->name ?? 'Green Valley' }}</h1>
<div class="sub">স্টাফ বেতন শিট — {{ $months[$sheet['month']] }} {{ $sheet['year'] }}</div>
<table>
    <thead>
        <tr><th>#</th><th>নাম</th><th>পদবি</th><th>মূল বেতন</th><th>ওভারটাইম</th><th>বোনাস</th><th>অগ্রিম কর্তন</th><th>নিট</th><th>অবস্থা</th><th>স্বাক্ষর</th></tr>
    </thead>
    <tbody>
    @foreach ($sheet['rows'] as $i => $r)
        <tr>
            <td>{{ $i + 1 }}</td><td>{{ $r['name'] }}</td><td>{{ $r['designation'] }}</td>
            <td class="num">{{ $f($r['basic']) }}</td><td class="num">{{ $f($r['overtime']) }}</td><td class="num">{{ $f($r['bonus']) }}</td>
            <td class="num">{{ $f($r['advance_deduction']) }}</td><td class="num">{{ $f($r['net']) }}</td><td>{{ $state[$r['state']] }}</td><td style="width:70px"></td>
        </tr>
    @endforeach
        <tr class="total"><td colspan="7">মোট</td><td class="num">{{ $f($sheet['totals']['net']) }}</td><td colspan="2">পরিশোধিত {{ $f($sheet['totals']['paid']) }}</td></tr>
    </tbody>
</table>
</body>
</html>
