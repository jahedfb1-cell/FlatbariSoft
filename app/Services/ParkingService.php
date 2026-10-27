<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\ParkingAllocation;
use App\Models\ParkingCharge;
use App\Models\ParkingPayment;
use App\Models\ParkingSlot;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Building parking: slots, who holds them, the monthly charge and its collection.
 *
 * Money follows the same rule as everywhere else: a payment row is written together with its cash/bank
 * line and a balanced journal entry (Dr cash/bank, Cr 4700 parking income), atomically. What a charge
 * still owes is always derived from its payments, never stored.
 */
class ParkingService
{
    private const INCOME_ACCOUNT = '4700';

    public function __construct(private readonly AccountingService $accounting) {}

    public function createSlot(array $data): ParkingSlot
    {
        return ParkingSlot::create($data + ['building_id' => \App\Models\Building::query()->value('id')]);
    }

    public function updateSlot(ParkingSlot $slot, array $data): ParkingSlot
    {
        $slot->update($data);

        return $slot->fresh();
    }

    /**
     * $data: flat_id, person_id?, vehicle_no?, from_date, monthly_fee? (defaults to the slot's), notes?
     * A slot can only be held by one flat at a time: release it before giving it to someone else.
     */
    public function allocate(ParkingSlot $slot, array $data): ParkingAllocation
    {
        return DB::transaction(function () use ($slot, $data) {
            $locked = ParkingSlot::whereKey($slot->id)->lockForUpdate()->firstOrFail();

            if (! $locked->is_active) {
                throw ValidationException::withMessages(['slot' => 'এই স্লটটি নিষ্ক্রিয়।']);
            }
            if ($locked->allocations()->whereNull('to_date')->exists()) {
                throw ValidationException::withMessages(['slot' => 'এই স্লট ইতিমধ্যে বরাদ্দ আছে — আগে ছেড়ে দিন।']);
            }

            return $locked->allocations()->create([
                'flat_id' => $data['flat_id'],
                'person_id' => $data['person_id'] ?? null,
                'vehicle_no' => $data['vehicle_no'] ?? null,
                'from_date' => $data['from_date'],
                'monthly_fee' => $data['monthly_fee'] ?? $locked->monthly_fee,
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    public function release(ParkingAllocation $allocation, string $toDate): ParkingAllocation
    {
        return DB::transaction(function () use ($allocation, $toDate) {
            $locked = ParkingAllocation::whereKey($allocation->id)->lockForUpdate()->firstOrFail();

            if ($locked->to_date !== null) {
                throw ValidationException::withMessages(['allocation' => 'এই বরাদ্দ আগেই শেষ করা হয়েছে।']);
            }
            if (Carbon::parse($toDate)->lt($locked->from_date)) {
                throw ValidationException::withMessages(['to_date' => 'শেষের তারিখ শুরুর তারিখের আগে হতে পারে না।']);
            }

            $locked->update(['to_date' => $toDate]);

            return $locked->fresh();
        });
    }

    /**
     * Charges one month for every allocation that covers any part of it, once each. Free allocations
     * (fee 0) produce no charge. Running it again for the same month adds nothing.
     *
     * @return array{created:int, skipped:int}
     */
    public function generateCharges(int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();

        return DB::transaction(function () use ($year, $month, $start, $end) {
            $created = 0;
            $skipped = 0;

            ParkingAllocation::where('from_date', '<=', $end->toDateString())
                ->where(fn ($q) => $q->whereNull('to_date')->orWhere('to_date', '>=', $start->toDateString()))
                ->get()
                ->each(function (ParkingAllocation $a) use ($year, $month, $end, &$created, &$skipped) {
                    if (bccomp((string) $a->monthly_fee, '0', 4) <= 0) {
                        return;
                    }
                    $charge = ParkingCharge::firstOrCreate(
                        ['parking_allocation_id' => $a->id, 'year' => $year, 'month' => $month],
                        ['amount' => $a->monthly_fee, 'due_date' => $end->toDateString()],
                    );
                    $charge->wasRecentlyCreated ? $created++ : $skipped++;
                });

            return ['created' => $created, 'skipped' => $skipped];
        });
    }

    /** $data: amount, method, paid_on, reference_no? - never more than the charge still owes. */
    public function recordPayment(ParkingCharge $charge, array $data, int $by): ParkingPayment
    {
        return DB::transaction(function () use ($charge, $data, $by) {
            $locked = ParkingCharge::whereKey($charge->id)->lockForUpdate()->firstOrFail();
            $amount = (string) $data['amount'];
            $due = $locked->due();

            if (bccomp($amount, $due, 4) > 0) {
                throw ValidationException::withMessages(['amount' => 'পরিমাণ বকেয়ার ('.$due.') বেশি হতে পারে না।']);
            }

            $payment = ParkingPayment::create([
                'parking_charge_id' => $locked->id,
                'amount' => $amount,
                'method' => $data['method'],
                'paid_on' => $data['paid_on'],
                'reference_no' => $data['reference_no'] ?? null,
                'created_by' => $by,
            ]);
            $payment->update(['receipt_no' => 'PK-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT)]);

            $slot = $locked->allocation->slot;
            $particulars = "পার্কিং {$slot->code} — {$locked->year}-".str_pad((string) $locked->month, 2, '0', STR_PAD_LEFT)." ({$payment->receipt_no})";
            $ledger = [
                'txn_date' => $data['paid_on'],
                'particulars' => $particulars,
                'fund_type' => 'OPERATING',
                'ref_type' => ParkingPayment::class,
                'ref_id' => $payment->id,
                'created_by' => $by,
            ];

            if ($data['method'] === 'CASH') {
                $this->accounting->postCashTransaction($ledger + ['in_amount' => $amount]);
                $asset = ChartOfAccount::where('code', '1200')->firstOrFail();
            } else {
                $bank = BankAccount::first();
                if (! $bank) {
                    throw ValidationException::withMessages(['bank' => 'কোনো ব্যাংক হিসাব সেটআপ করা নেই।']);
                }
                $this->accounting->postBankTransaction($bank, $ledger + ['debit' => $amount]);
                $asset = ChartOfAccount::where('code', '1100')->firstOrFail();
            }

            $income = ChartOfAccount::where('code', self::INCOME_ACCOUNT)->firstOrFail();
            $entry = $this->accounting->postJournal(
                [['account_id' => $asset->id, 'debit' => $amount], ['account_id' => $income->id, 'credit' => $amount]],
                $particulars,
                $data['paid_on'],
                $by,
                $by,
            );
            $payment->update(['journal_entry_id' => $entry->id]);

            return $payment->fresh();
        });
    }

    /** Charged / collected / still due for one month, all derived from the rows. */
    public function summary(int $year, int $month): array
    {
        $charged = (string) (ParkingCharge::where('year', $year)->where('month', $month)->sum('amount') ?: 0);
        $collected = (string) (ParkingPayment::whereIn('parking_charge_id', ParkingCharge::where('year', $year)->where('month', $month)->select('id'))->sum('amount') ?: 0);

        return [
            'year' => $year,
            'month' => $month,
            'charged' => bcadd($charged, '0', 4),
            'collected' => bcadd($collected, '0', 4),
            'due' => bcsub($charged, $collected, 4),
            'slots_total' => ParkingSlot::where('is_active', true)->count(),
            'slots_taken' => ParkingAllocation::whereNull('to_date')->count(),
        ];
    }
}
