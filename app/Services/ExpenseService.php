<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Confirmed rule #9: Sinking Fund spending has its own approval ladder on
 * top of the normal Maker-Checker — ৳25,000+ needs two distinct admins to
 * approve (not the same admin twice), ৳100,000+ additionally requires a
 * meeting resolution to already be linked. Every other expense just needs
 * one approval, same as a payment.
 */
class ExpenseService
{
    public function __construct(private readonly AccountingService $accounting, private readonly TreasuryService $treasury) {}

    public function create(array $data, int $createdBy): Expense
    {
        // an expense for a one-off work is paid from the project fund, and only while the work is open
        if (! \Illuminate\Support\Facades\Schema::hasColumn('expenses', 'work_budget_id')) {
            unset($data['work_budget_id']);
        } elseif (! empty($data['work_budget_id'])) {
            app(WorkBudgetService::class)->checkExpense(\App\Models\WorkBudget::findOrFail($data['work_budget_id']), (string) $data['amount']);
            $data['fund_type'] = 'CAPITAL';
        }

        return Expense::create(array_merge($data, [
            'created_by' => $createdBy,
            'status' => 'PENDING',
            'fund_type' => $data['fund_type'] ?? 'OPERATING',
        ]));
    }

    /**
     * The record this expense was raised from, if any - a gas or material purchase, a salary, an advance, a water bill.
     * Those have their own screens that keep both sides in step, so the plain expense form must not touch them.
     *
     * @return array{label: string, model: \Illuminate\Database\Eloquent\Model}|null
     */
    public function linkedSource(Expense $expense): ?array
    {
        $sources = [
            ['gas_purchases', \App\Models\GasPurchase::class, 'গ্যাস ক্রয়ের পাতা'],
            ['construction_material_purchases', \App\Models\ConstructionMaterialPurchase::class, 'নির্মাণ সামগ্রীর পাতা'],
            ['salary_payments', \App\Models\SalaryPayment::class, 'স্টাফ বেতনের পাতা'],
            ['staff_advances', \App\Models\StaffAdvance::class, 'স্টাফ অগ্রিমের পাতা'],
            ['water_bills', \App\Models\WaterBill::class, 'পানির বিলের পাতা'],
        ];
        foreach ($sources as [$table, $model, $label]) {
            if (\Illuminate\Support\Facades\Schema::hasTable($table) && \Illuminate\Support\Facades\Schema::hasColumn($table, 'expense_id')) {
                $row = $model::where('expense_id', $expense->id)->first();
                if ($row) {
                    return ['label' => $label, 'model' => $row];
                }
            }
        }

        return null;
    }

    /** A plain expense can be corrected while nobody has approved it; once an approver has signed, what they saw must not change. */
    public function isEditable(Expense $expense): bool
    {
        return $expense->status === 'PENDING' && ! $expense->approvals()->exists() && $this->linkedSource($expense) === null;
    }

    /** @param  array<string, mixed>  $data */
    public function update(Expense $expense, array $data): Expense
    {
        if ($expense->status !== 'PENDING') {
            throw ValidationException::withMessages(['expense' => 'এই খরচ অনুমোদিত হয়ে গেছে — আর বদলানো যাবে না।']);
        }
        if ($expense->approvals()->exists()) {
            throw ValidationException::withMessages(['expense' => 'একজন অনুমোদনকারী ইতিমধ্যে এই খরচ দেখে সই করেছেন — আর বদলানো যাবে না। প্রয়োজনে প্রত্যাখ্যান করে নতুন করে এন্ট্রি দিন।']);
        }
        if ($source = $this->linkedSource($expense)) {
            throw ValidationException::withMessages(['expense' => "এই খরচ {$source['label']} থেকে তৈরি — সেখান থেকেই সংশোধন করুন, নইলে দুই জায়গার টাকা মিলবে না।"]);
        }

        if (! \Illuminate\Support\Facades\Schema::hasColumn('expenses', 'work_budget_id')) {
            unset($data['work_budget_id']);
        } elseif (! empty($data['work_budget_id'])) {
            app(WorkBudgetService::class)->checkExpense(\App\Models\WorkBudget::findOrFail($data['work_budget_id']), (string) $data['amount']);
            $data['fund_type'] = 'CAPITAL';
        }

        $fields = array_intersect_key($data, array_flip(['date', 'expense_head_id', 'payee', 'amount', 'description', 'treasury_medium', 'fund_type', 'resolution_id']));
        if (\Illuminate\Support\Facades\Schema::hasColumn('expenses', 'work_budget_id')) {
            $fields['work_budget_id'] = $data['work_budget_id'] ?? null; // taking the work off is a correction too
        }
        $expense->update($fields);

        return $expense->fresh();
    }

    /**
     * An approver turns a pending expense down. Nothing has reached the books, so it is taken back whole: the expense, and
     * the purchase / salary / advance / bill it was raised from, are removed; the reason is written to the audit log and
     * the person who entered it is told. Approved expenses are in the ledger and are never touched here.
     */
    public function reject(Expense $expense, int $by, string $reason): void
    {
        if ($expense->status !== 'PENDING') {
            throw ValidationException::withMessages(['expense' => 'অনুমোদিত খরচ প্রত্যাখ্যান করা যায় না — ভুল হলে হিসাবের উল্টো এন্ট্রি দিতে হয়।']);
        }

        $facts = ['date' => $expense->date?->toDateString(), 'payee' => $expense->payee, 'amount' => (string) $expense->amount, 'description' => $expense->description];
        $creatorId = $expense->created_by;

        DB::transaction(function () use ($expense, $by, $reason, $facts) {
            if ($source = $this->linkedSource($expense)) {
                $row = $source['model'];
                $file = $row->invoice_file ?? null;
                $row->delete();
                if ($file) {
                    app(InvoiceImageService::class)->delete($file);
                }
            }
            $expense->delete();

            if (\Illuminate\Support\Facades\Schema::hasTable('audit_logs')) {
                \App\Models\AuditLog::create([
                    'user_id' => $by, 'event' => 'rejected', 'auditable_type' => 'Expense', 'auditable_id' => $expense->id,
                    'old_values' => $facts, 'new_values' => ['reason' => $reason], 'ip' => request()?->ip(),
                ]);
            }
        });

        // told only after it is done; a failure here never undoes the rejection
        try {
            if ($creatorId && \Illuminate\Support\Facades\Schema::hasTable('notifications') && ($creator = User::find($creatorId))) {
                $creator->notify(new \App\Notifications\ExpenseRejectedNotification(
                    'আপনার খরচ প্রত্যাখ্যান করা হয়েছে',
                    sprintf('%s — ৳%s (%s)। কারণ: %s', $facts['payee'], number_format((float) $facts['amount'], 2), $facts['date'], $reason),
                ));
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Rejection notice failed: '.$e->getMessage());
        }
    }

    public function requiredApprovals(Expense $expense): int
    {
        return $expense->fund_type === 'SINKING' && bccomp((string) $expense->amount, '25000', 4) >= 0 ? 2 : 1;
    }

    /** Is there anyone besides $except who may approve expenses? (a one-admin building must still be able to run) */
    private function anotherApproverExists(int $except): bool
    {
        return User::where('id', '!=', $except)->get()->contains(fn (User $u) => $u->hasRole('super_admin') || $u->can('expense.approve'));
    }

    public function approve(Expense $expense, int $approvedBy, ?string $note = null): Expense
    {
        if ($expense->status === 'APPROVED') {
            throw ValidationException::withMessages(['status' => 'এই খরচ ইতিমধ্যে অনুমোদিত।']);
        }

        if ($expense->fund_type === 'SINKING'
            && bccomp((string) $expense->amount, '100000', 4) >= 0
            && ! $expense->resolution_id) {
            throw ValidationException::withMessages([
                'resolution_id' => '১,০০,০০০ টাকার বেশি সঞ্চিত তহবিলের খরচের জন্য মিটিং রেজল্যুশন লিংক করা আবশ্যক।',
            ]);
        }

        if ($expense->approvals()->where('approved_by', $approvedBy)->exists()) {
            throw ValidationException::withMessages(['approved_by' => 'আপনি ইতিমধ্যে এই খরচ অনুমোদন করেছেন — অন্য একজন এডমিনের অনুমোদন প্রয়োজন।']);
        }

        // maker-checker: whoever entered the expense does not also approve it - unless nobody else is able to
        if ($expense->created_by === $approvedBy && $this->anotherApproverExists($approvedBy)) {
            throw ValidationException::withMessages(['approved_by' => 'যিনি খরচ এন্ট্রি করেছেন তিনি নিজে অনুমোদন করতে পারবেন না — অন্য একজন অনুমোদনকারী প্রয়োজন।']);
        }

        if ($expense->work_budget_id) {
            app(WorkBudgetService::class)->checkExpense($expense->workBudget, (string) $expense->amount, $expense->id, forApproval: true);
        }

        // a fund can never spend more than it holds
        $held = $expense->treasury_medium === 'CASH'
            ? $this->treasury->cashBalance($expense->fund_type)
            : $this->treasury->bankBalance($expense->fund_type);
        if (bccomp((string) $expense->amount, $held, 4) > 0) {
            throw ValidationException::withMessages(['amount' => ($expense->treasury_medium === 'CASH' ? 'ক্যাশে' : 'ব্যাংকে').' এই তহবিলের যথেষ্ট টাকা নেই (আছে '.$held.', খরচ '.bcadd((string) $expense->amount, '0', 4).')। আগে ওপেনিং ব্যালেন্স বা জমা এন্ট্রি করুন।']);
        }

        return DB::transaction(function () use ($expense, $approvedBy, $note) {
            $expense->approvals()->create(array_filter([
                'approved_by' => $approvedBy,
                'approved_at' => now(),
                // the note column arrives with the database upgrade
                'note' => \Illuminate\Support\Facades\Schema::hasColumn('expense_approvals', 'note') ? $note : null,
            ], fn ($v) => $v !== null));

            if ($expense->approvals()->count() < $this->requiredApprovals($expense)) {
                return $expense->fresh(); // still waiting on a second approver
            }

            $voucherNo = 'VOU-'.str_pad((string) $expense->id, 6, '0', STR_PAD_LEFT);
            $expense->update(['status' => 'APPROVED', 'approved_by' => $approvedBy, 'voucher_no' => $voucherNo]);

            $particulars = "Expense Voucher {$voucherNo} — {$expense->payee}";

            if ($expense->treasury_medium === 'CASH') {
                $this->accounting->postCashTransaction([
                    'txn_date' => $expense->date, 'particulars' => $particulars, 'out_amount' => $expense->amount,
                    'fund_type' => $expense->fund_type, 'ref_type' => Expense::class, 'ref_id' => $expense->id,
                    'created_by' => $approvedBy,
                ]);
            } else {
                $bank = BankAccount::first();
                if (! $bank) {
                    throw ValidationException::withMessages(['bank' => 'কোনো ব্যাংক হিসাব সেটআপ করা নেই।']);
                }
                $this->accounting->postBankTransaction($bank, [
                    'txn_date' => $expense->date, 'particulars' => $particulars, 'credit' => $expense->amount,
                    'fund_type' => $expense->fund_type, 'ref_type' => Expense::class, 'ref_id' => $expense->id,
                    'created_by' => $approvedBy,
                ]);
            }

            $expenseAccount = $expense->expenseHead->account
                ?? ChartOfAccount::where('code', '5900')->firstOrFail();
            $assetAccount = ChartOfAccount::where('code', $expense->treasury_medium === 'CASH' ? '1200' : '1100')->firstOrFail();

            $this->accounting->postJournal(
                [
                    ['account_id' => $expenseAccount->id, 'debit' => $expense->amount],
                    ['account_id' => $assetAccount->id, 'credit' => $expense->amount],
                ],
                $particulars,
                $expense->date,
                $approvedBy,
                $approvedBy,
            );

            return $expense->fresh();
        });
    }
}
