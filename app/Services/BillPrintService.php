<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\BillingPeriod;
use App\Models\GasMeterReading;
use App\Support\PdfName;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The month's bill as a paper slip for each flat: service charge, the gas used (last reading, this reading, units, rate),
 * the previous balance and what is to be paid. The manager prints them, four to an A4 page, and hands one to every flat -
 * or prints one flat's bill alone. The same figures are what the flat's own people see in their portal.
 */
class BillPrintService
{
    /**
     * @return Collection<int, array<string, mixed>> one slip per bill, flats in building order (2A, 2B ... 10E)
     */
    public function slips(int $year, int $month, ?int $flatId = null): Collection
    {
        $period = BillingPeriod::where('year', $year)->where('month', $month)->first();
        if (! $period || ! $period->bills()->exists()) {
            throw ValidationException::withMessages(['month' => 'এই মাসের বিল এখনও তৈরি হয়নি।']);
        }

        $bills = $period->bills()->with('flat.currentOwners.person', 'flat.currentTenant.person')
            ->when($flatId, fn ($q) => $q->where('flat_id', $flatId))->get();
        if ($bills->isEmpty()) {
            throw ValidationException::withMessages(['flat_id' => 'এই ফ্ল্যাটের এই মাসের বিল নেই।']);
        }

        // the meter figures of the month, by flat
        $readings = GasMeterReading::where('billing_period_id', $period->id)->with('consumer')->get()
            ->filter(fn ($r) => $r->consumer?->flat_id)->keyBy(fn ($r) => $r->consumer->flat_id);

        // flat order: 1A, 1B, 2A ... 9B, 10A - numbers as numbers, not as text
        $slips = $bills->sort(fn (Bill $a, Bill $b) => strnatcasecmp((string) $a->flat->flat_no, (string) $b->flat->flat_no))->values()
            ->map(fn (Bill $b) => $this->slip($b, $readings->get($b->flat_id), $period));

        // the whole month's print also carries the project office's own gas, last: not payable, but on paper with the rest
        if (! $flatId) {
            $first = $slips->first(fn ($s) => $s['gas_rate'] !== null);
            $rate = $first['gas_rate'] ?? null;
            foreach (GasMeterReading::where('billing_period_id', $period->id)->with('consumer')->get()->filter(fn ($r) => $r->consumer && ($r->consumer->is_internal || ! $r->consumer->flat_id)) as $r) {
                $slips->push($this->officeSlip($r, $period, $rate));
            }
        }

        return $slips->values();
    }

    /** The project office's gas for the month, laid out like a flat's slip but never payable. */
    private function officeSlip(GasMeterReading $r, BillingPeriod $period, ?string $rate): array
    {
        $units = (string) $r->units_used;
        $amount = $rate !== null ? bcmul($units, $rate, 2) : '0';

        return [
            'is_office' => true,
            'bill_id' => null,
            'flat_no' => 'Project Office',
            'owner' => null,
            'tenant' => null,
            'year' => $period->year,
            'month' => $period->month,
            'month_label' => PdfName::month($period->year, $period->month),
            'service_charge' => '0',
            'gas_amount' => $amount,
            'gas_previous' => (string) $r->previous_reading,
            'gas_current' => (string) $r->current_reading,
            'gas_units' => $units,
            'gas_rate' => $rate,
            'other_charges' => '0', 'late_fee' => '0', 'previous_due' => '0',
            'total_payable' => $amount, 'paid_amount' => '0', 'due' => '0',
            'due_date' => null, 'due_date_iso' => null, 'status' => 'INTERNAL',
        ];
    }

    /** One bill's slip - for a resident looking at their own bill. */
    public function slipFor(Bill $bill): array
    {
        $bill->loadMissing('flat.currentOwners.person', 'flat.currentTenant.person', 'billingPeriod');
        $reading = GasMeterReading::where('billing_period_id', $bill->billing_period_id)
            ->whereHas('consumer', fn ($q) => $q->where('flat_id', $bill->flat_id))->with('consumer')->first();

        return $this->slip($bill, $reading, $bill->billingPeriod);
    }

    /** @return array<string, mixed> */
    private function slip(Bill $b, ?GasMeterReading $r, BillingPeriod $period): array
    {
        $units = $r ? (string) $r->units_used : null;
        $names = $b->flat->occupantNames();

        return [
            'bill_id' => $b->id,
            'flat_no' => $b->flat->flat_no,
            'owner' => $names['owner'] ? str_replace(', ', ' & ', $names['owner']) : null,
            'tenant' => $names['tenant'] ? str_replace(', ', ' & ', $names['tenant']) : null,
            'year' => $period->year,
            'month' => $period->month,
            'month_label' => PdfName::month($period->year, $period->month),
            'service_charge' => (string) $b->service_charge,
            'gas_amount' => (string) $b->gas_amount,
            'gas_previous' => $r ? (string) $r->previous_reading : null,
            'gas_current' => $r ? (string) $r->current_reading : null,
            'gas_units' => $units,
            // the rate the bill was made with, worked back from its own gas amount so the slip always adds up
            'gas_rate' => $units !== null && bccomp($units, '0', 4) > 0 ? bcdiv((string) $b->gas_amount, $units, 2) : null,
            'other_charges' => (string) $b->other_charges,
            'late_fee' => (string) $b->late_fee,
            'previous_due' => (string) $b->previous_due,
            'total_payable' => (string) $b->total_payable,
            'paid_amount' => (string) $b->paid_amount,
            'due' => bcsub((string) $b->total_payable, (string) $b->paid_amount, 4),
            'due_date' => $b->due_date?->format('d M Y'),
            'due_date_iso' => $b->due_date?->toDateString(),
            'status' => $b->status,
        ];
    }
}
