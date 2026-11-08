<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateBillingPeriodRequest;
use App\Http\Requests\UpdateBillRequest;
use App\Http\Resources\BillResource;
use App\Models\Bill;
use App\Models\BillingPeriod;
use App\Models\Building;
use App\Models\Flat;
use App\Services\BillGenerationService;
use App\Services\BillPrintService;
use App\Support\PdfName;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class BillingController extends Controller
{
    public function __construct(private readonly BillGenerationService $service) {}

    public function generate(GenerateBillingPeriodRequest $request)
    {
        $period = $this->service->generate(
            $request->validated('year'),
            $request->validated('month'),
            Auth::id(),
            $request->boolean('notify', true),
            ! $request->boolean('allow_without_gas'),
        );

        return response()->json([
            'message' => 'বিল জেনারেট হয়েছে।',
            'period_id' => $period->id,
            'bill_count' => $period->bills()->count(),
        ], 201);
    }

    /** The twelve months of a July-June year, each with its bill count and totals - months nobody billed yet come back empty so the register can always be opened. */
    public function months(Request $request)
    {
        $fy = (int) ($request->query('fiscal_year') ?: (now('Asia/Dhaka')->month >= 7 ? now('Asia/Dhaka')->year : now('Asia/Dhaka')->year - 1));
        $out = [];
        for ($i = 0; $i < 12; $i++) {
            $m = (($i + 6) % 12) + 1;
            $y = $m >= 7 ? $fy : $fy + 1;
            $period = BillingPeriod::where('year', $y)->where('month', $m)->first();
            $row = $period ? $period->bills()->selectRaw('COUNT(*) c, COALESCE(SUM(total_payable),0) billed, COALESCE(SUM(paid_amount),0) paid')->first() : null;
            $billed = (string) ($row->billed ?? 0);
            $paid = (string) ($row->paid ?? 0);
            $out[] = [
                'year' => $y, 'month' => $m, 'bill_count' => (int) ($row->c ?? 0),
                'billed' => bcadd($billed, '0', 4), 'paid' => bcadd($paid, '0', 4), 'due' => bcsub($billed, $paid, 4),
            ];
        }

        return response()->json(['fiscal_year' => $fy, 'label' => $fy.'-'.substr((string) ($fy + 1), 2), 'months' => $out]);
    }

    public function update(UpdateBillRequest $request, Bill $bill)
    {
        $bill = $this->service->correct($bill, $request->validated());
        Log::info('Bill corrected', ['bill_id' => $bill->id, 'by' => Auth::id(), 'reason' => $request->validated('reason')]);

        return new BillResource($bill->load('flat.currentOwners.person', 'flat.currentTenant.person'));
    }

    /**
     * The month's bills on paper, four to an A4 page: every flat in one file, or one flat alone (?flat_id=).
     * Named like the owners' other papers - "10A-Nazrul Islam-Bill Sep 2026.pdf" / "All Flat Bills-Sep 2026.pdf".
     */
    public function pdf(Request $request, int $year, int $month, BillPrintService $print)
    {
        $flatId = $request->filled('flat_id') ? (int) $request->query('flat_id') : null;
        $slips = $print->slips($year, $month, $flatId);

        $name = $flatId
            ? PdfName::forOwner(Flat::findOrFail($flatId), 'Bill '.PdfName::month($year, $month))
            : PdfName::make('All Flat Bills', PdfName::month($year, $month));

        return PdfName::titled(Pdf::loadView('reports.bill-slips', ['building' => Building::first(), 'slips' => $slips])->setPaper('a4', 'portrait'), $name)->stream($name);
    }

    public function show(int $year, int $month)
    {
        // a month nobody has billed yet is an empty register, not an error
        $period = BillingPeriod::where('year', $year)->where('month', $month)->first();
        if (! $period) {
            return response()->json([]);
        }

        $bills = $period->bills()->with('flat.currentOwners.person', 'flat.currentTenant.person')
            ->withSum(['payments as pending_amount' => fn ($q) => $q->where('status', 'PENDING')], 'amount')
            ->get()
            // flat order as the building reads it (1A, 1B, 2A ... 10A), not the order the flats were typed in
            ->sort(fn ($a, $b) => strnatcasecmp((string) $a->flat?->flat_no, (string) $b->flat?->flat_no))->values();

        // a flat's balance moves forward into its newest bill, so money is taken there: tell the screen which bill that is
        $key = $year * 100 + $month;
        $latest = \Illuminate\Support\Facades\DB::table('bills')
            ->join('billing_periods as bp', 'bills.billing_period_id', '=', 'bp.id')
            ->whereIn('bills.flat_id', $bills->pluck('flat_id'))
            ->whereRaw('(bp.year * 100 + bp.month) > ?', [$key])
            ->selectRaw('bills.flat_id, MAX(bp.year * 100 + bp.month) as k')->groupBy('bills.flat_id')->pluck('k', 'flat_id');
        $bills->each(function ($bill) use ($latest) {
            if ($k = $latest[$bill->flat_id] ?? null) {
                $bill->setAttribute('newest_year', intdiv((int) $k, 100));
                $bill->setAttribute('newest_month', (int) $k % 100);
            }
        });

        return BillResource::collection($bills);
    }
}
