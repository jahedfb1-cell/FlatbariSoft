<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\BillingPeriod;
use App\Models\CollectionHead;
use App\Models\Flat;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Monthly service-charge bill generation for every active flat.
 *
 * Gas amount starts at 0 here — GasBillingService (Phase 1.5) fills it in
 * per flat once meter readings exist for the period, then updates the
 * bill's total_payable. Generating a period before meter readings are in
 * just means gas_amount stays 0 until then; it does not block billing.
 */
class BillGenerationService
{
    public function generate(int $year, int $month, int $generatedBy): BillingPeriod
    {
        // Explicit 'status' => 'DRAFT': Eloquent doesn't reflect a column's
        // DB-level default in the in-memory model right after create(), so
        // without this the freshly created row's $period->status would be
        // null (not 'DRAFT') and the check below would misfire immediately.
        $period = BillingPeriod::firstOrCreate(['year' => $year, 'month' => $month], ['status' => 'DRAFT']);

        if ($period->status !== 'DRAFT') {
            throw ValidationException::withMessages([
                'period' => "{$year}-{$month} মাসের বিল ইতিমধ্যে জেনারেট করা হয়েছে।",
            ]);
        }

        return DB::transaction(function () use ($period, $year, $month, $generatedBy) {
            $dueDate = Carbon::create($year, $month, app(SettingsService::class)->int('bill_due_day'));
            $serviceChargeHead = CollectionHead::where('code', 'SERVICE_CHARGE')->firstOrFail();
            $currentKey = $year * 100 + $month;

            $flats = Flat::where('status', 'ACTIVE')->get();

            foreach ($flats as $flat) {
                $serviceCharge = $flat->occupancy_status === 'EMPTY'
                    ? $flat->empty_flat_rate
                    : $flat->service_charge_rate;

                // Outstanding balance carried from the most recent earlier
                // period this flat was billed in — never assumes "last
                // month" specifically, in case a period was skipped.
                $prevBill = Bill::query()
                    ->join('billing_periods as bp', 'bills.billing_period_id', '=', 'bp.id')
                    ->where('bills.flat_id', $flat->id)
                    ->whereRaw('(bp.year * 100 + bp.month) < ?', [$currentKey])
                    ->orderByRaw('(bp.year * 100 + bp.month) desc')
                    ->select('bills.*')
                    ->first();

                $previousDue = $prevBill
                    ? max(0, (float) bcsub((string) $prevBill->total_payable, (string) $prevBill->paid_amount, 4))
                    : 0;

                $totalPayable = bcadd((string) $serviceCharge, (string) $previousDue, 4);

                $bill = Bill::create([
                    'billing_period_id' => $period->id,
                    'flat_id' => $flat->id,
                    'service_charge' => $serviceCharge,
                    'gas_amount' => 0,
                    'other_charges' => 0,
                    'late_fee' => 0,
                    'previous_due' => $previousDue,
                    'total_payable' => $totalPayable,
                    'paid_amount' => 0,
                    'due_date' => $dueDate,
                    'status' => 'UNPAID',
                ]);

                $bill->items()->create([
                    'collection_head_id' => $serviceChargeHead->id,
                    'description' => $serviceChargeHead->name_bn,
                    'amount' => $serviceCharge,
                ]);
            }

            $period->update(['status' => 'GENERATED', 'generated_by' => $generatedBy]);

            return $period->fresh();
        });
    }
}
