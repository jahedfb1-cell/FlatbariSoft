<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\SalaryPayment;
use App\Services\SalaryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SalaryController extends Controller
{
    public function __construct(private readonly SalaryService $service) {}

    private function period(Request $request): array
    {
        $today = now('Asia/Dhaka');
        $v = $request->validate(['year' => ['nullable', 'integer', 'between:2020,2100'], 'month' => ['nullable', 'integer', 'between:1,12']]);

        return [(int) ($v['year'] ?? $today->year), (int) ($v['month'] ?? $today->month)];
    }

    public function sheet(Request $request)
    {
        [$y, $m] = $this->period($request);

        return response()->json($this->service->sheet($y, $m));
    }

    public function generate(Request $request)
    {
        $v = $request->validate(['year' => ['required', 'integer', 'between:2020,2100'], 'month' => ['required', 'integer', 'between:1,12']]);
        $made = $this->service->generate($v['year'], $v['month'], Auth::id());

        return response()->json(['made' => $made] + $this->service->sheet($v['year'], $v['month']));
    }

    public function update(Request $request, SalaryPayment $salaryPayment)
    {
        $v = $request->validate([
            'basic' => ['sometimes', 'numeric', 'min:0', 'max:99999999'],
            'overtime' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999999'],
            'bonus' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999999'],
            'advance_deduction' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999999'],
        ]);
        $this->service->update($salaryPayment, array_map(fn ($x) => $x ?? 0, $v));

        return response()->json($this->service->sheet($salaryPayment->year, $salaryPayment->month));
    }

    public function pay(Request $request, SalaryPayment $salaryPayment)
    {
        $v = $request->validate(['treasury_medium' => ['required', Rule::in(['CASH', 'BANK'])]]);
        $this->service->pay($salaryPayment, $v['treasury_medium'], Auth::id());

        return response()->json($this->service->sheet($salaryPayment->year, $salaryPayment->month));
    }

    public function payAll(Request $request)
    {
        $v = $request->validate(['year' => ['required', 'integer', 'between:2020,2100'], 'month' => ['required', 'integer', 'between:1,12'], 'treasury_medium' => ['required', Rule::in(['CASH', 'BANK'])]]);
        $paid = $this->service->payAll($v['year'], $v['month'], $v['treasury_medium'], Auth::id());

        return response()->json(['paid' => $paid] + $this->service->sheet($v['year'], $v['month']));
    }

    public function advances()
    {
        return response()->json($this->service->advances());
    }

    public function giveAdvance(Request $request)
    {
        $v = $request->validate([
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'amount' => ['required', 'numeric', 'min:1', 'max:99999999'],
            'monthly_deduction' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'given_date' => ['nullable', 'date', 'before_or_equal:'.now('Asia/Dhaka')->toDateString()],
            'note' => ['nullable', 'string', 'max:255'],
            'treasury_medium' => ['required', Rule::in(['CASH', 'BANK'])],
        ], ['amount.min' => 'অগ্রিমের পরিমাণ শূন্যের বেশি হতে হবে।', 'given_date.before_or_equal' => 'ভবিষ্যতের তারিখ দেওয়া যাবে না।']);
        $this->service->giveAdvance($v, Auth::id());

        return response()->json($this->service->advances(), 201);
    }

    /** One staff member's payslip for a month - only once the row exists. */
    public function payslip(SalaryPayment $salaryPayment)
    {
        $salaryPayment->load('staff', 'expense');

        return Pdf::loadView('reports.payslip', ['building' => Building::first(), 'p' => $salaryPayment])
            ->setPaper('a5', 'portrait')->stream("payslip-{$salaryPayment->staff->id}-{$salaryPayment->year}-{$salaryPayment->month}.pdf");
    }

    public function sheetPdf(Request $request)
    {
        [$y, $m] = $this->period($request);

        return Pdf::loadView('reports.salary-sheet', ['building' => Building::first(), 'sheet' => $this->service->sheet($y, $m)])
            ->setPaper('a4', 'landscape')->stream("salary-sheet-{$y}-{$m}.pdf");
    }
}
