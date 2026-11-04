<?php

namespace App\Services;

use App\Models\Bill;
use Illuminate\Support\Collection;

/**
 * What flats still owe. Every month's bill already carries the unpaid balance of the month before (previous_due), so the
 * bills of earlier months must never be added up as well: a flat's balance is the unpaid amount of its LATEST bill, and the
 * building's total is the sum of those. (Adding every unpaid bill counts the same money again for each month it stayed unpaid.)
 */
class BillBalanceService
{
    /** @return Collection<int, Bill> each flat's most recent bill (up to the given year/month when asked), with its flat and period loaded */
    public function latestPerFlat(?int $year = null, ?int $month = null): Collection
    {
        $limit = $year && $month ? $year * 100 + $month : null;

        return Bill::with('flat.currentOwners.person', 'flat.currentTenant.person', 'billingPeriod')->get()
            ->filter(fn (Bill $b) => $b->billingPeriod && ($limit === null || $b->billingPeriod->year * 100 + $b->billingPeriod->month <= $limit))
            ->sortBy(fn (Bill $b) => $b->billingPeriod->year * 100 + $b->billingPeriod->month)
            ->groupBy('flat_id')->map(fn ($bills) => $bills->last())->values();
    }

    /** What one bill still has unpaid (never below zero). */
    public function due(Bill $b): string
    {
        $d = bcsub((string) $b->total_payable, (string) $b->paid_amount, 4);

        return bccomp($d, '0', 4) > 0 ? $d : '0.0000';
    }

    /** The gas part of a bill's unpaid amount: payments are credited across a bill's parts in proportion, so the unpaid part splits the same way. */
    public function gasPart(Bill $b): string
    {
        if (bccomp((string) $b->total_payable, '0', 4) <= 0) {
            return '0.0000';
        }

        return bcdiv(bcmul($this->due($b), (string) $b->gas_amount, 8), (string) $b->total_payable, 4);
    }

    /** Everything the building is still owed by flats. */
    public function totalDue(): string
    {
        return $this->latestPerFlat()->reduce(fn ($c, Bill $b) => bcadd($c, $this->due($b), 4), '0');
    }

    public function totalGasDue(): string
    {
        return $this->latestPerFlat()->reduce(fn ($c, Bill $b) => bcadd($c, $this->gasPart($b), 4), '0');
    }
}
