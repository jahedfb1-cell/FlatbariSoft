<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\BillingPeriod;
use App\Models\CollectionHead;
use App\Models\Flat;
use App\Models\GasMeterReading;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Monthly service-charge bill generation for every active flat.
 *
 * Gas amount starts at 0 here — unless the month's gas readings are already in, in which case they are applied
 * straight away (the office is asked to enter them first). Otherwise GasBillingService fills it in
 * per flat once meter readings exist for the period, then updates the
 * bill's total_payable. The screen stops the office when readings are missing and only
 * goes on if it confirms (allow_without_gas); then gas_amount stays 0 until it is added.
 */
class BillGenerationService
{
    public function generate(int $year, int $month, int $generatedBy, bool $notify = true, bool $requireGas = false): BillingPeriod
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

        // the gas figures must be in before the bill is made, or the flat is told (and billed) without its gas
        if ($requireGas) {
            $missing = $this->flatsWithoutGasReading($period);
            if ($missing > 0) {
                throw ValidationException::withMessages(['gas' => "{$missing}টি ফ্ল্যাটের গ্যাস রিডিং এখনও দেওয়া হয়নি। আগে গ্যাস পাতায় রিডিং দিন, তারপর বিল তৈরি করুন।"]);
            }
        }

        $generated = DB::transaction(function () use ($period, $year, $month, $generatedBy) {
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

            // readings already entered for this month go straight into the bills
            if (GasMeterReading::where('billing_period_id', $period->id)->exists()) {
                app(GasBillingService::class)->applyToBills($period->id);
            }

            $period->update(['status' => 'GENERATED', 'generated_by' => $generatedBy]);

            return $period->fresh();
        });

        // told only once the bills are safely saved; this can never undo or block them
        if ($notify) {
            app(BillNotifier::class)->billsGenerated($generated);
        }

        return $generated;
    }

    /** How many active flats have no gas reading entered for this month (no consumer at all counts as none). */
    public function flatsWithoutGasReading(BillingPeriod $period): int
    {
        $read = GasMeterReading::where('billing_period_id', $period->id)
            ->join('gas_consumers', 'gas_consumers.id', '=', 'gas_meter_readings.gas_consumer_id')
            ->whereNotNull('gas_consumers.flat_id')->pluck('gas_consumers.flat_id');

        return Flat::where('status', 'ACTIVE')->whereNotIn('id', $read)->count();
    }

    /**
     * Correct the amounts of one bill (a wrong service charge, a gas figure that was missed, an extra charge).
     * The total is recomputed here, never trusted from the screen. Only the flat's latest bill can change - a later
     * month has already carried its balance forward - and the total can never drop below what was already paid.
     */
    public function correct(Bill $bill, array $amounts): Bill
    {
        return DB::transaction(function () use ($bill, $amounts) {
            $bill = Bill::whereKey($bill->id)->lockForUpdate()->firstOrFail();
            $key = $bill->billingPeriod->year * 100 + $bill->billingPeriod->month;

            $later = Bill::query()
                ->join('billing_periods as bp', 'bills.billing_period_id', '=', 'bp.id')
                ->where('bills.flat_id', $bill->flat_id)
                ->whereRaw('(bp.year * 100 + bp.month) > ?', [$key])
                ->exists();
            if ($later) {
                throw ValidationException::withMessages(['bill' => 'এই ফ্ল্যাটের পরের মাসের বিল ইতিমধ্যে হয়ে গেছে — আগের মাসের বিল আর বদলানো যাবে না।']);
            }

            $total = (string) $bill->previous_due;
            foreach (['service_charge', 'gas_amount', 'other_charges', 'late_fee'] as $k) {
                $total = bcadd($total, bcadd((string) $amounts[$k], '0', 4), 4);
            }
            if (bccomp($total, (string) $bill->paid_amount, 4) < 0) {
                throw ValidationException::withMessages(['service_charge' => 'মোট বিল ইতিমধ্যে পরিশোধিত টাকার ('.bcadd((string) $bill->paid_amount, '0', 2).') চেয়ে কম হতে পারে না।']);
            }

            $paid = (string) $bill->paid_amount;
            $status = bccomp($paid, $total, 4) >= 0 ? 'PAID' : (bccomp($paid, '0', 4) > 0 ? 'PARTIAL' : 'UNPAID');

            $bill->update([
                'service_charge' => $amounts['service_charge'], 'gas_amount' => $amounts['gas_amount'],
                'other_charges' => $amounts['other_charges'], 'late_fee' => $amounts['late_fee'],
                'total_payable' => $total, 'status' => $status,
            ]);

            return $bill->fresh();
        });
    }
}
