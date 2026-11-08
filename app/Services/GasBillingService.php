<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Flat;
use App\Models\GasConsumer;
use App\Models\GasMeterReading;
use App\Models\GasPurchase;
use App\Models\GasRateSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GasBillingService
{
    /**
     * Every flat is a gas consumer. Flats are added without anyone registering a meter for them, so the consumer
     * list calls this first: any flat without a consumer gets one (named after the flat, meter number copied from
     * the flat). Idempotent; existing consumers are never touched, and the project office stays internal.
     */
    public function syncFlatConsumers(): void
    {
        $have = GasConsumer::whereNotNull('flat_id')->pluck('flat_id')->all();

        Flat::whereNotIn('id', $have)->orderBy('id')->get()->each(fn (Flat $flat) => GasConsumer::create([
            'flat_id' => $flat->id,
            'name' => 'Flat '.$flat->flat_no,
            'meter_no' => $flat->gas_meter_no,
            'is_internal' => false,
            'active' => true,
        ]));
    }

    /**
     * Confirmed rule #4: gas bill = (current - previous) × rate. Rejects a
     * current reading below the previous one outright — a meter cannot run
     * backwards; that combination means a data-entry mistake, not a real
     * reading.
     */
    public function recordReading(GasConsumer $consumer, int $billingPeriodId, float $currentReading, string $readingDate, int $enteredBy, ?string $photo = null, ?string $firstPrevious = null): GasMeterReading
    {
        // typing the same month's reading again is a correction: it replaces the earlier one
        $existing = GasMeterReading::where('gas_consumer_id', $consumer->id)->where('billing_period_id', $billingPeriodId)->first();

        // the meter's last reading is the one from the closest EARLIER month - never a later month's
        $previous = $this->previousReadingFor($consumer, \App\Models\BillingPeriod::findOrFail($billingPeriodId));

        // the very first month has no earlier reading: the meter's reading at the start is given by hand (0 if it was new)
        $previousReading = $previous?->current_reading ?? ($firstPrevious !== null && $firstPrevious !== '' ? $firstPrevious : 0);

        if ($currentReading < $previousReading) {
            throw ValidationException::withMessages([
                'current_reading' => 'বর্তমান রিডিং আগের রিডিং ('.$previousReading.') থেকে কম হতে পারে না।',
            ]);
        }

        $unitsUsed = bcsub((string) $currentReading, (string) $previousReading, 4);

        $values = [
            'previous_reading' => $previousReading,
            'current_reading' => $currentReading,
            'units_used' => $unitsUsed,
            'reading_date' => $readingDate,
            'entered_by' => $enteredBy,
            'photo' => $photo ?? $existing?->photo,
        ];

        if ($existing) {
            $existing->update($values);

            return $existing->fresh();
        }

        return GasMeterReading::create(['gas_consumer_id' => $consumer->id, 'billing_period_id' => $billingPeriodId] + $values);
    }

    /** The consumer's reading from the closest earlier month, or null when this is its first month. */
    public function previousReadingFor(GasConsumer $consumer, \App\Models\BillingPeriod $period): ?GasMeterReading
    {
        return GasMeterReading::where('gas_meter_readings.gas_consumer_id', $consumer->id)
            ->join('billing_periods as bp', 'bp.id', '=', 'gas_meter_readings.billing_period_id')
            ->whereRaw('(bp.year * 100 + bp.month) < ?', [$period->year * 100 + $period->month])
            ->orderByRaw('(bp.year * 100 + bp.month) desc')
            ->select('gas_meter_readings.*')
            ->first();
    }

    /**
     * The numbers at the top of the gas page: everything ever bought, what was bought this month, and what the
     * flats (and the project office) used on their meters this month, with the system loss between the two.
     * Usage is the sum of the month's readings - a flat that has not been read yet simply isn't counted.
     *
     * @return array<string, mixed>
     */
    public function summary(int $year, int $month): array
    {
        $m3PerKg = (string) (GasRateSetting::latest('effective_from')->first()?->m3_per_kg ?? 0.54);

        $all = GasPurchase::selectRaw('COUNT(*) c, COALESCE(SUM(quantity_kg),0) kg, COALESCE(SUM(total_cost),0) cost')->first();
        $mon = GasPurchase::whereYear('date', $year)->whereMonth('date', $month)
            ->selectRaw('COUNT(*) c, COALESCE(SUM(quantity_kg),0) kg, COALESCE(SUM(total_cost),0) cost')->first();

        $period = \App\Models\BillingPeriod::where('year', $year)->where('month', $month)->first();
        $flatUse = $officeUse = '0';
        $flatsRead = 0;
        if ($period) {
            $rows = GasMeterReading::where('billing_period_id', $period->id)->with('consumer')->get();
            foreach ($rows as $r) {
                if ($r->consumer?->is_internal || ! $r->consumer?->flat_id) {
                    $officeUse = bcadd($officeUse, (string) $r->units_used, 4);
                } else {
                    $flatUse = bcadd($flatUse, (string) $r->units_used, 4);
                    $flatsRead++;
                }
            }
        }
        $flatCount = GasConsumer::where('active', true)->where('is_internal', false)->whereNotNull('flat_id')->count();
        $totalUse = bcadd($flatUse, $officeUse, 4);
        try {
            $billRate = $this->ratePerUnit($year, $month);
        } catch (ValidationException) {
            $billRate = ['rate' => null, 'source' => 'NONE'];
        }
        $boughtM3 = bcmul((string) $mon->kg, $m3PerKg, 4);

        return [
            'year' => $year,
            'month' => $month,
            'total_purchases' => (int) $all->c,
            'total_kg' => bcadd((string) $all->kg, '0', 4),
            'total_cost' => bcadd((string) $all->cost, '0', 4),
            'month_purchases' => (int) $mon->c,
            'month_kg' => bcadd((string) $mon->kg, '0', 4),
            'month_cost' => bcadd((string) $mon->cost, '0', 4),
            'month_bought_m3' => $boughtM3,
            'flats_used_m3' => $flatUse,
            'office_used_m3' => $officeUse,
            'total_used_m3' => $totalUse,
            'flats_read' => $flatsRead,
            'flats_total' => $flatCount,
            'bill_rate' => $billRate['rate'],
            'bill_rate_source' => $billRate['source'],
            'loss_m3' => bcsub($boughtM3, $totalUse, 4),
            'loss_percent' => bccomp($boughtM3, '0', 4) > 0 ? round(((float) bcsub($boughtM3, $totalUse, 4) / (float) $boughtM3) * 100, 2) : null,
        ];
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

    /** The rate setting in force for a month (the one that began on or before its last day). */
    private function settingFor(int $year, int $month): ?GasRateSetting
    {
        $end = \Carbon\Carbon::create($year, $month, 1)->endOfMonth();

        return GasRateSetting::where('effective_from', '<=', $end)->latest('effective_from')->first()
            ?? GasRateSetting::latest('effective_from')->first();
    }

    /**
     * What one unit of gas costs the flats for a month: the price the gas was actually bought at that month
     * (everything paid for it, transport included, over the units it came to), not a number typed once and left.
     * A month with no purchase of its own uses the latest earlier month that had one; only if gas was never bought
     * does the fixed rate from the settings apply.
     *
     * @return array{rate: string, source: string}  source: PURCHASE | EARLIER_PURCHASE | FIXED
     */
    public function ratePerUnit(int $year, int $month): array
    {
        $m3PerKg = (string) ($this->settingFor($year, $month)?->m3_per_kg ?? 0.54);

        $perUnit = function (string $from, string $to) use ($m3PerKg): ?string {
            $row = GasPurchase::whereBetween('date', [$from, $to])->selectRaw('COALESCE(SUM(quantity_kg),0) kg, COALESCE(SUM(total_cost),0) cost')->first();
            $units = bcmul((string) $row->kg, $m3PerKg, 4);

            return bccomp($units, '0', 4) > 0 ? number_format((float) bcdiv((string) $row->cost, $units, 6), 2, '.', '') : null;
        };

        $first = \Carbon\Carbon::create($year, $month, 1);
        if ($own = $perUnit($first->toDateString(), $first->copy()->endOfMonth()->toDateString())) {
            return ['rate' => $own, 'source' => 'PURCHASE'];
        }

        $lastEarlier = GasPurchase::where('date', '<', $first->toDateString())->where('quantity_kg', '>', 0)->max('date');
        if ($lastEarlier) {
            $m = \Carbon\Carbon::parse($lastEarlier)->startOfMonth();
            if ($earlier = $perUnit($m->toDateString(), $m->copy()->endOfMonth()->toDateString())) {
                return ['rate' => $earlier, 'source' => 'EARLIER_PURCHASE'];
            }
        }

        $fixed = $this->settingFor($year, $month);
        if (! $fixed) {
            throw ValidationException::withMessages(['rate' => 'কোনো গ্যাস রেট সেটিং পাওয়া যায়নি, আর কোনো গ্যাস কেনাও নেই।']);
        }

        return ['rate' => (string) $fixed->rate, 'source' => 'FIXED'];
    }

    /**
     * Confirmed rule #6: the project office is a consumer too, but its
     * reading is never billed to a flat — it's an internal expense
     * (booked in Phase 1.7's expense flow, not here). Only non-internal
     * consumers with a flat get their bill updated.
     */
    public function applyToBills(int $billingPeriodId): void
    {
        $period = \App\Models\BillingPeriod::findOrFail($billingPeriodId);
        $setting = $this->settingFor($period->year, $period->month);
        $perUnit = $this->ratePerUnit($period->year, $period->month);
        $rate = (object) ['rate' => $perUnit['rate'], 'minimum_charge' => $setting?->minimum_charge ?? 0];

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
