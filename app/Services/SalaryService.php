<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\SalaryPayment;
use App\Models\Staff;
use App\Models\StaffAdvance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Paying the staff. Each month has a salary sheet (one row per active staff member); a row is paid by an ordinary
 * expense, so it goes through the same approval, fund and ledger checks as every other payment. An advance given to
 * a staff member is recorded on its own and is deducted from later salaries; what is still owed on advances is always
 * worked out from the records, never stored. Every figure is computed here.
 */
class SalaryService
{
    public function __construct(private readonly ExpenseService $expenses, private readonly SettingsService $settings) {}

    public function ready(): bool
    {
        return Schema::hasTable('staff_advances') && Schema::hasColumn('salary_payments', 'expense_id');
    }

    // ---- advances

    /** What a staff member still owes on advances that were actually paid out (their expense is approved), less what salaries have taken back. */
    public function outstanding(int $staffId, ?int $exceptRowId = null): string
    {
        if (! $this->ready()) {
            return '0';
        }
        $given = (string) (StaffAdvance::where('staff_id', $staffId)->whereHas('expense', fn ($q) => $q->where('status', 'APPROVED'))->sum('amount') ?: 0);
        $taken = (string) (SalaryPayment::where('staff_id', $staffId)->whereNotNull('expense_id')->when($exceptRowId, fn ($q) => $q->where('id', '!=', $exceptRowId))->sum('advance_deduction') ?: 0);

        $left = bcsub($given, $taken, 4);

        return bccomp($left, '0', 4) > 0 ? $left : '0.0000';
    }

    /** The deduction a new salary row starts with: the agreed monthly amount, never more than is owed. */
    private function suggestedDeduction(int $staffId): string
    {
        $outstanding = $this->outstanding($staffId);
        if (bccomp($outstanding, '0', 4) <= 0) {
            return '0.0000';
        }
        $monthly = (string) (StaffAdvance::where('staff_id', $staffId)->whereHas('expense', fn ($q) => $q->where('status', 'APPROVED'))->sum('monthly_deduction') ?: 0);

        return bccomp($monthly, $outstanding, 4) > 0 ? bcadd($outstanding, '0', 4) : bcadd($monthly, '0', 4);
    }

    /** @param  array{staff_id:int, amount:string|int|float, monthly_deduction?:string|int|float|null, given_date?:string, note?:?string, treasury_medium:string}  $data */
    public function giveAdvance(array $data, int $by): StaffAdvance
    {
        $this->mustBeReady();
        $staff = Staff::where('status', 'ACTIVE')->findOrFail($data['staff_id']);
        $amount = bcadd((string) $data['amount'], '0', 4);
        $monthly = bcadd((string) ($data['monthly_deduction'] ?? 0), '0', 4);
        if (bccomp($monthly, $amount, 4) > 0) {
            throw ValidationException::withMessages(['monthly_deduction' => 'মাসিক কর্তন অগ্রিমের চেয়ে বেশি হতে পারে না।']);
        }

        return DB::transaction(function () use ($data, $staff, $amount, $monthly, $by) {
            $expense = $this->expenses->create([
                'date' => $data['given_date'] ?? now('Asia/Dhaka')->toDateString(),
                'expense_head_id' => ExpenseHead::where('code', 'STAFF_SALARY')->firstOrFail()->id,
                'payee' => $staff->name,
                'amount' => $amount,
                'description' => 'স্টাফ অগ্রিম বেতন — '.$staff->name,
                'treasury_medium' => $data['treasury_medium'],
                'fund_type' => 'OPERATING',
            ], $by);

            return StaffAdvance::create([
                'staff_id' => $staff->id, 'amount' => $amount, 'monthly_deduction' => $monthly,
                'given_date' => $data['given_date'] ?? now('Asia/Dhaka')->toDateString(), 'note' => $data['note'] ?? null,
                'expense_id' => $expense->id, 'created_by' => $by,
            ]);
        });
    }

    /** @return array<string, mixed> every advance, newest first, with how much of it is still to be taken back per person */
    public function advances(): array
    {
        if (! $this->ready()) {
            return ['ready' => false, 'advances' => [], 'owed' => []];
        }
        $advances = StaffAdvance::with('staff', 'expense')->latest('given_date')->latest('id')->get()->map(fn (StaffAdvance $a) => [
            'id' => $a->id, 'staff_id' => $a->staff_id, 'staff_name' => $a->staff?->name, 'amount' => (string) $a->amount,
            'monthly_deduction' => (string) $a->monthly_deduction, 'given_date' => $a->given_date->toDateString(), 'note' => $a->note,
            'status' => $a->expense?->status === 'APPROVED' ? 'PAID_OUT' : 'AWAITING_APPROVAL', 'voucher_no' => $a->expense?->voucher_no,
        ])->all();
        $owed = Staff::orderBy('name')->get()->map(fn (Staff $s) => ['staff_id' => $s->id, 'name' => $s->name, 'outstanding' => $this->outstanding($s->id)])
            ->filter(fn ($r) => bccomp($r['outstanding'], '0', 4) > 0)->values()->all();

        return ['ready' => true, 'advances' => $advances, 'owed' => $owed];
    }

    // ---- the monthly sheet

    public function dueDay(): int
    {
        return $this->settings->int('salary_due_day');
    }

    /**
     * One month: a row for every active staff member (a generated one, or a not-yet-generated placeholder) with where
     * it stands - unpaid, awaiting approval, or paid - and whether it is late.
     *
     * @return array<string, mixed>
     */
    public function sheet(int $year, int $month): array
    {
        $today = now('Asia/Dhaka');
        $dueDate = Carbon::create($year, $month, min($this->dueDay(), 28), 0, 0, 0, 'Asia/Dhaka');
        $ready = $this->ready();
        $payments = Schema::hasTable('salary_payments')
            ? SalaryPayment::with('expense')->where('year', $year)->where('month', $month)->get()->keyBy('staff_id')
            : collect();

        $rows = [];
        $totals = ['net' => '0', 'paid' => '0', 'unpaid' => '0'];
        foreach (Staff::orderBy('name')->get() as $s) {
            $p = $payments[$s->id] ?? null;
            if (! $p && $s->status !== 'ACTIVE') {
                continue;
            }
            $state = ! $p ? 'NOT_GENERATED' : (! $p->expense_id ? 'UNPAID' : ($p->expense?->status === 'APPROVED' ? 'PAID' : 'AWAITING_APPROVAL'));
            $net = $p ? (string) $p->net_amount : bcadd((string) $s->monthly_salary, '0', 4);
            $rows[] = [
                'staff_id' => $s->id, 'name' => $s->name, 'designation' => $s->designation, 'mobile' => $s->mobile,
                'id' => $p?->id, 'state' => $state,
                'basic' => $p ? (string) $p->basic : (string) $s->monthly_salary,
                'overtime' => $p ? (string) $p->overtime : '0.0000', 'bonus' => $p ? (string) $p->bonus : '0.0000',
                'advance_deduction' => $p ? (string) $p->advance_deduction : '0.0000', 'net' => bcadd($net, '0', 4),
                'advance_outstanding' => $this->outstanding($s->id, $p?->id), 'suggested_deduction' => $ready ? $this->suggestedDeduction($s->id) : '0.0000',
                'paid_date' => $p?->paid_date?->toDateString(), 'treasury_medium' => $p?->treasury_medium, 'voucher_no' => $p?->expense?->voucher_no,
                'late' => in_array($state, ['NOT_GENERATED', 'UNPAID'], true) && $today->greaterThan($dueDate->copy()->endOfDay()),
            ];
            $totals['net'] = bcadd($totals['net'], $net, 4);
            $totals[$state === 'PAID' ? 'paid' : 'unpaid'] = bcadd($totals[$state === 'PAID' ? 'paid' : 'unpaid'], $net, 4);
        }

        return [
            'year' => $year, 'month' => $month, 'due_date' => $dueDate->toDateString(), 'due_day' => $this->dueDay(), 'ready' => $ready,
            'rows' => $rows, 'totals' => array_map(fn ($v) => bcadd($v, '0', 4), $totals),
            'late_count' => count(array_filter($rows, fn ($r) => $r['late'])),
        ];
    }

    /** Creates the month's row for every active staff member who has joined by then and has none yet. Returns how many were made. */
    public function generate(int $year, int $month, int $by): int
    {
        $this->mustBeReady();
        $monthEnd = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();
        $made = 0;

        DB::transaction(function () use ($year, $month, $by, $monthEnd, &$made) {
            foreach (Staff::where('status', 'ACTIVE')->where('join_date', '<=', $monthEnd)->get() as $s) {
                if (SalaryPayment::where('staff_id', $s->id)->where('year', $year)->where('month', $month)->exists()) {
                    continue;
                }
                $basic = bcadd((string) $s->monthly_salary, '0', 4);
                $ded = bccomp($this->suggestedDeduction($s->id), $basic, 4) > 0 ? $basic : $this->suggestedDeduction($s->id);
                SalaryPayment::create([
                    'staff_id' => $s->id, 'year' => $year, 'month' => $month, 'basic' => $basic, 'overtime' => 0, 'bonus' => 0,
                    'advance_deduction' => $ded, 'net_amount' => bcsub($basic, $ded, 4), 'status' => 'PENDING', 'created_by' => $by,
                ]);
                $made++;
            }
        });

        return $made;
    }

    /** @param  array{basic?:mixed, overtime?:mixed, bonus?:mixed, advance_deduction?:mixed}  $data */
    public function update(SalaryPayment $row, array $data): SalaryPayment
    {
        if ($row->expense_id) {
            throw ValidationException::withMessages(['salary' => 'এই মাসের বেতন দেওয়ার এন্ট্রি হয়ে গেছে — আর বদলানো যাবে না।']);
        }
        $basic = bcadd((string) ($data['basic'] ?? $row->basic), '0', 4);
        $overtime = bcadd((string) ($data['overtime'] ?? $row->overtime), '0', 4);
        $bonus = bcadd((string) ($data['bonus'] ?? $row->bonus), '0', 4);
        $ded = bcadd((string) ($data['advance_deduction'] ?? $row->advance_deduction), '0', 4);

        $gross = bcadd(bcadd($basic, $overtime, 4), $bonus, 4);
        $owed = $this->outstanding($row->staff_id, $row->id);
        if (bccomp($ded, $owed, 4) > 0) {
            throw ValidationException::withMessages(['advance_deduction' => 'অগ্রিম কর্তন বাকি অগ্রিমের ('.bcadd($owed, '0', 2).') চেয়ে বেশি হতে পারে না।']);
        }
        if (bccomp($ded, $gross, 4) > 0) {
            throw ValidationException::withMessages(['advance_deduction' => 'অগ্রিম কর্তন মোট বেতনের চেয়ে বেশি হতে পারে না।']);
        }

        $row->update(['basic' => $basic, 'overtime' => $overtime, 'bonus' => $bonus, 'advance_deduction' => $ded, 'net_amount' => bcsub($gross, $ded, 4)]);

        return $row->fresh();
    }

    /** Pays one row: an ordinary expense is created (and then waits for approval like any other). */
    public function pay(SalaryPayment $row, string $medium, int $by): SalaryPayment
    {
        return DB::transaction(function () use ($row, $medium, $by) {
            $row = SalaryPayment::with('staff')->whereKey($row->id)->lockForUpdate()->firstOrFail();
            if ($row->expense_id) {
                throw ValidationException::withMessages(['salary' => 'এই মাসের বেতন আগেই দেওয়ার এন্ট্রি হয়েছে।']);
            }
            if (bccomp((string) $row->net_amount, '0', 4) <= 0) {
                throw ValidationException::withMessages(['salary' => 'নিট বেতন শূন্যের বেশি হতে হবে।']);
            }
            // the deduction is checked again now, so two sheets can never take back the same advance twice
            if (bccomp((string) $row->advance_deduction, $this->outstanding($row->staff_id, $row->id), 4) > 0) {
                throw ValidationException::withMessages(['advance_deduction' => 'অগ্রিম কর্তন বাকি অগ্রিমের চেয়ে বেশি — আগে ঠিক করুন।']);
            }

            $expense = $this->expenses->create([
                'date' => now('Asia/Dhaka')->toDateString(),
                'expense_head_id' => ExpenseHead::where('code', 'STAFF_SALARY')->firstOrFail()->id,
                'payee' => $row->staff->name,
                'amount' => $row->net_amount,
                'description' => 'স্টাফ বেতন — '.$row->staff->name.' — '.$row->month.'/'.$row->year,
                'treasury_medium' => $medium,
                'fund_type' => 'OPERATING',
            ], $by);
            $row->update(['expense_id' => $expense->id, 'paid_date' => now('Asia/Dhaka')->toDateString(), 'treasury_medium' => $medium]);

            return $row->fresh();
        });
    }

    /** Pays every row of the month that is not paid yet. Returns how many were paid. */
    public function payAll(int $year, int $month, string $medium, int $by): int
    {
        $paid = 0;
        foreach (SalaryPayment::where('year', $year)->where('month', $month)->whereNull('expense_id')->where('net_amount', '>', 0)->get() as $row) {
            $this->pay($row, $medium, $by);
            $paid++;
        }

        return $paid;
    }

    /** Staff with no salary paid (or entered) for the month - what the dashboard nudges about after the due date. */
    public function unpaidCount(int $year, int $month): int
    {
        if (! Schema::hasTable('salary_payments') || ! Schema::hasColumn('salary_payments', 'expense_id')) {
            return 0;
        }
        $monthEnd = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();
        $active = Staff::where('status', 'ACTIVE')->where('join_date', '<=', $monthEnd)->pluck('id');
        $done = SalaryPayment::where('year', $year)->where('month', $month)->whereNotNull('expense_id')->pluck('staff_id');

        return $active->diff($done)->count();
    }

    private function mustBeReady(): void
    {
        if (! $this->ready()) {
            throw ValidationException::withMessages(['salary' => 'ডাটাবেস আপগ্রেড বাকি আছে — আপগ্রেড ফাইল ইমপোর্ট করার পর বেতন শিট ব্যবহার করা যাবে।']);
        }
    }
}
