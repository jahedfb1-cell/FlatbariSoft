<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\WaterBill;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * The monthly Dhaka WASA water bill. The bill is entered as printed (readings, water, sewerage, VAT ...); the total is
 * always worked out here from those lines. Posting it creates an ordinary expense, so it goes through the same approval,
 * ledger and fund checks as any other payment. Nothing about WASA's tariff is stored: the rates change, the bill is the truth.
 */
class WaterBillService
{
    private const LINES = ['water_charge', 'sewerage_charge', 'vat', 'other_charge', 'late_fee'];

    public function __construct(private readonly ExpenseService $expenses, private readonly ExpenseDetailService $papers) {}

    public function ready(): bool
    {
        return Schema::hasTable('water_bills');
    }

    /** @param  array<string, mixed>  $data  @return WaterBill */
    public function record(array $data, int $by): WaterBill
    {
        $this->mustBeReady();
        $account = trim((string) ($data['account_no'] ?? ''));

        if (WaterBill::where('bill_year', $data['bill_year'])->where('bill_month', $data['bill_month'])->where('account_no', $account)->exists()) {
            throw ValidationException::withMessages(['bill_month' => 'এই মাসের (এই অ্যাকাউন্টের) ওয়াসার বিল আগেই পোস্ট করা আছে।']);
        }

        return DB::transaction(function () use ($data, $by, $account) {
            $figures = $this->figures($data);
            $head = ExpenseHead::where('code', 'UTILITY')->firstOrFail();

            $expense = $this->expenses->create([
                'date' => now('Asia/Dhaka')->toDateString(),
                'expense_head_id' => $head->id,
                'payee' => 'ঢাকা ওয়াসা',
                'amount' => $figures['total'],
                'description' => $this->description($data),
                'treasury_medium' => $data['treasury_medium'],
                'fund_type' => 'OPERATING',
            ], $by);

            if (! empty($data['attachments']) && Schema::hasTable('expense_attachments')) {
                $this->papers->attach($expense, $data['attachments'], $by);
            }

            return WaterBill::create($figures + [
                'bill_year' => $data['bill_year'], 'bill_month' => $data['bill_month'], 'account_no' => $account,
                'bill_no' => $data['bill_no'] ?? null, 'due_date' => $data['due_date'] ?? null, 'notes' => $data['notes'] ?? null,
                'expense_id' => $expense->id, 'created_by' => $by,
            ]);
        });
    }

    /** A bill can be corrected only while its expense still waits for approval; after that it is in the books. */
    public function update(WaterBill $bill, array $data): WaterBill
    {
        $expense = $bill->expense;
        if ($expense->status !== 'PENDING') {
            throw ValidationException::withMessages(['bill' => 'এই বিলের খরচ অনুমোদিত হয়ে গেছে — আর বদলানো যাবে না।']);
        }

        return DB::transaction(function () use ($bill, $expense, $data) {
            $merged = array_merge($bill->only(['previous_reading', 'current_reading', ...self::LINES]), $data);
            $figures = $this->figures($merged);
            $expense->update(array_filter([
                'amount' => $figures['total'],
                'treasury_medium' => $data['treasury_medium'] ?? null,
                'description' => $this->description(['bill_year' => $bill->bill_year, 'bill_month' => $bill->bill_month] + $data),
            ], fn ($v) => $v !== null));
            $bill->update($figures + array_intersect_key($data, array_flip(['bill_no', 'due_date', 'notes'])));

            return $bill->fresh();
        });
    }

    /**
     * Twelve months of a July-June year, each with its bill (or none) and where the money stands.
     *
     * @return array<string, mixed>
     */
    public function months(int $fiscalYear): array
    {
        $bills = $this->ready()
            ? WaterBill::with('expense')->where(fn ($q) => $q->where(fn ($a) => $a->where('bill_year', $fiscalYear)->where('bill_month', '>=', 7))->orWhere(fn ($b) => $b->where('bill_year', $fiscalYear + 1)->where('bill_month', '<=', 6)))
                ->orderBy('bill_year')->orderBy('bill_month')->get()->groupBy(fn ($b) => $b->bill_year * 100 + $b->bill_month)
            : collect();

        $today = now('Asia/Dhaka');
        $months = [];
        $total = '0';
        $paid = '0';
        for ($i = 0; $i < 12; $i++) {
            $m = (($i + 6) % 12) + 1;
            $y = $m >= 7 ? $fiscalYear : $fiscalYear + 1;
            $rows = ($bills[$y * 100 + $m] ?? collect())->map(function (WaterBill $b) use ($today, &$total, &$paid) {
                $approved = $b->expense?->status === 'APPROVED';
                $total = bcadd($total, (string) $b->total, 4);
                if ($approved) {
                    $paid = bcadd($paid, (string) $b->total, 4);
                }

                return $this->present($b) + [
                    'status' => $approved ? 'PAID' : ($b->due_date && $b->due_date->toDateString() < $today->toDateString() ? 'OVERDUE' : 'PENDING'),
                ];
            })->values()->all();

            $months[] = ['year' => $y, 'month' => $m, 'bills' => $rows, 'status' => $rows === [] ? 'NOT_POSTED' : $this->worst($rows)];
        }

        return [
            'fiscal_year' => $fiscalYear, 'label' => $fiscalYear.'-'.substr((string) ($fiscalYear + 1), 2),
            'months' => $months, 'total' => bcadd($total, '0', 4), 'paid' => bcadd($paid, '0', 4), 'ready' => $this->ready(),
            // the last bill's closing reading and account carry over to pre-fill the next one
            'last' => $this->ready() ? ($this->lastBill() ? ['account_no' => $this->lastBill()->account_no, 'current_reading' => $this->lastBill()->current_reading] : null) : null,
        ];
    }

    /** @return array<string, mixed> */
    public function present(WaterBill $b): array
    {
        return [
            'id' => $b->id, 'year' => $b->bill_year, 'month' => $b->bill_month, 'account_no' => $b->account_no, 'bill_no' => $b->bill_no,
            'previous_reading' => $b->previous_reading, 'current_reading' => $b->current_reading, 'consumption' => $b->consumption,
            'water_charge' => (string) $b->water_charge, 'sewerage_charge' => (string) $b->sewerage_charge, 'vat' => (string) $b->vat,
            'other_charge' => (string) $b->other_charge, 'late_fee' => (string) $b->late_fee, 'total' => (string) $b->total,
            'due_date' => $b->due_date?->toDateString(), 'notes' => $b->notes,
            'expense_id' => $b->expense_id, 'expense_status' => $b->expense?->status, 'voucher_no' => $b->expense?->voucher_no,
            'treasury_medium' => $b->expense?->treasury_medium,
        ];
    }

    /** A month holding several bills reads as its least settled one. */
    private function worst(array $rows): string
    {
        foreach (['OVERDUE', 'PENDING'] as $s) {
            if (collect($rows)->contains('status', $s)) {
                return $s;
            }
        }

        return 'PAID';
    }

    private function lastBill(): ?WaterBill
    {
        return WaterBill::orderByDesc('bill_year')->orderByDesc('bill_month')->orderByDesc('id')->first();
    }

    /** The money lines, the consumption and the total - all derived here. @return array<string, string|null> */
    private function figures(array $d): array
    {
        $out = [];
        $total = '0';
        foreach (self::LINES as $l) {
            $out[$l] = bcadd((string) ($d[$l] ?? 0), '0', 4);
            $total = bcadd($total, $out[$l], 4);
        }
        $prev = $d['previous_reading'] ?? null;
        $curr = $d['current_reading'] ?? null;
        $out['previous_reading'] = $prev !== null && $prev !== '' ? bcadd((string) $prev, '0', 4) : null;
        $out['current_reading'] = $curr !== null && $curr !== '' ? bcadd((string) $curr, '0', 4) : null;
        $out['consumption'] = $out['previous_reading'] !== null && $out['current_reading'] !== null ? bcsub($out['current_reading'], $out['previous_reading'], 4) : null;
        $out['total'] = $total;
        if (bccomp($total, '0', 4) <= 0) {
            throw ValidationException::withMessages(['water_charge' => 'বিলের মোট টাকা শূন্যের বেশি হতে হবে।']);
        }

        return $out;
    }

    private function description(array $d): string
    {
        $months = ['জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];

        return 'ওয়াসা পানি বিল — '.($months[((int) ($d['bill_month'] ?? 1)) - 1] ?? '').' '.($d['bill_year'] ?? '');
    }

    private function mustBeReady(): void
    {
        if (! $this->ready()) {
            throw ValidationException::withMessages(['bill_month' => 'ডাটাবেস আপগ্রেড বাকি আছে — আপগ্রেড ফাইল ইমপোর্ট করার পর ওয়াসার বিল পোস্ট করা যাবে।']);
        }
    }
}
