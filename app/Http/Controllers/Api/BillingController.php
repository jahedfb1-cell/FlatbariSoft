<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateBillingPeriodRequest;
use App\Http\Requests\UpdateBillRequest;
use App\Http\Resources\BillResource;
use App\Models\Bill;
use App\Models\BillingPeriod;
use App\Services\BillGenerationService;
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

    public function show(int $year, int $month)
    {
        $period = BillingPeriod::where('year', $year)->where('month', $month)->firstOrFail();

        return BillResource::collection(
            $period->bills()->with('flat.currentOwners.person', 'flat.currentTenant.person')
                ->withSum(['payments as pending_amount' => fn ($q) => $q->where('status', 'PENDING')], 'amount')
                ->orderBy('flat_id')->get()
        );
    }
}
