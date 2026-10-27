<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\GasConsumer;
use App\Models\GasMeterReading;
use App\Models\GasPurchase;
use App\Models\GasRateSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GasBillingService
{
    /**
     * Confirmed rule #4: gas bill = (current - previous) × rate. Rejects a
     * current reading below the previous one outright — a meter cannot run
     * backwards; that combination means a data-entry mistake, not a real
     * reading.
     */
    public function recordReading(GasConsumer $consumer, int $billingPeriodId, float $currentReading, string $readingDate, int $enteredBy, ?string $photo = null): GasMeterReading
    {
        $previous = GasMeterReading::where('gas_consumer_id', $consumer->id)
            ->orderByDesc('id')
            ->first();

        $previousReading = $previous?->current_reading ?? 0;

        if ($currentReading < $previousReading) {
            throw ValidationException::withMessages([
                'current_reading' => 'বর্তমান রিডিং আগের রিডিং ('.$previousReading.') থেকে কম হতে পারে না।',
            ]);
        }

        $unitsUsed = bcsub((string) $currentReading, (string) $previousReading, 4);

        return GasMeterReading::create([
            'gas_consumer_id' => $consumer->id,
            'billing_period_id' => $billingPeriodId,
            'previous_reading' => $previousReading,
            'current_reading' => $currentReading,
            'units_used' => $unitsUsed,
            'reading_date' => $readingDate,
            'entered_by' => $enteredBy,
            'photo' => $photo,
        ]);
    }

    /**
     * Confirmed rule #5: System Loss % = (purchased LPG's m³ equivalent −
     * sum of every consumer's units_used) ÷ purchased m³ equivalent × 100.
     * Purchases are matched to the billing period by calendar month/year
     * (gas_purchases has no billing_period_id of its own).
     */
    public function systemLossPercent(int $year, int $month, int $billingPeriodId): ?float
    {
        $purchasedKg = GasPurchase::whereYear('date', $year)->whereMonth('date', $month)->sum('quantity_kg');

        if ((float) $purchasedKg <= 0) {
            return null;
        }

        $m3PerKg = (float) (GasRateSetting::latest('effective_from')->first()?->m3_per_kg ?? 0.54);
        $purchasedM3 = bcmul((string) $purchasedKg, (string) $m3PerKg, 4);

        $readM3 = GasMeterReading::where('billing_period_id', $billingPeriodId)->sum('units_used');

        $loss = bcsub($purchasedM3, (string) $readM3, 4);

        return round(((float) $loss / (float) $purchasedM3) * 100, 2);
    }

    /**
     * Confirmed rule #6: the project office is a consumer too, but its
     * reading is never billed to a flat — it's an internal expense
     * (booked in Phase 1.7's expense flow, not here). Only non-internal
     * consumers with a flat get their bill updated.
     */
    public function applyToBills(int $billingPeriodId): void
    {
        $rate = GasRateSetting::where('effective_from', '<=', now())->latest('effective_from')->first();

        if (! $rate) {
            throw ValidationException::withMessages(['rate' => 'কোনো গ্যাস রেট সেটিং পাওয়া যায়নি।']);
        }

        $readings = GasMeterReading::where('billing_period_id', $billingPeriodId)
            ->whereHas('consumer', fn ($q) => $q->where('is_internal', false)->whereNotNull('flat_id'))
            ->with('consumer')
            ->get();

        DB::transaction(function () use ($readings, $rate, $billingPeriodId) {
            foreach ($readings as $reading) {
                $gasAmount = max((string) $rate->minimum_charge, bcmul((string) $reading->units_used, (string) $rate->rate, 4));

                $bill = Bill::where('billing_period_id', $billingPeriodId)
                    ->where('flat_id', $reading->consumer->flat_id)
                    ->first();

                if (! $bill) {
                    continue;
                }

                $newTotal = bcadd(
                    bcsub((string) $bill->total_payable, (string) $bill->gas_amount, 4),
                    $gasAmount,
                    4,
                );

                $bill->update(['gas_amount' => $gasAmount, 'total_payable' => $newTotal]);
            }
        });
    }
}
