<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\Expense;
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
    public function __construct(private readonly AccountingService $accounting) {}

    public function create(array $data, int $createdBy): Expense
    {
        return Expense::create(array_merge($data, [
            'created_by' => $createdBy,
            'status' => 'PENDING',
            'fund_type' => $data['fund_type'] ?? 'OPERATING',
        ]));
    }

    public function requiredApprovals(Expense $expense): int
    {
        return $expense->fund_type === 'SINKING' && bccomp((string) $expense->amount, '25000', 4) >= 0 ? 2 : 1;
    }

    public function approve(Expense $expense, int $approvedBy): Expense
    {
        if ($expense->status === 'APPROVED') {
            throw ValidationException::withMessages(['status' => 'এই খরচ ইতিমধ্যে অনুমোদিত।']);
        }

        if ($expense->fund_type === 'SINKING'
            && bccomp((string) $expense->amount, '100000', 4) >= 0
            && ! $expense->resolution_id) {
            throw ValidationException::withMessages([
                'resolution_id' => '১,০০,০০০ টাকার বেশি Sinking Fund খরচের জন্য মিটিং রেজল্যুশন লিংক করা আবশ্যক।',
            ]);
        }

        if ($expense->approvals()->where('approved_by', $approvedBy)->exists()) {
            throw ValidationException::withMessages(['approved_by' => 'আপনি ইতিমধ্যে এই খরচ অনুমোদন করেছেন — অন্য একজন এডমিনের অনুমোদন প্রয়োজন।']);
        }

        return DB::transaction(function () use ($expense, $approvedBy) {
            $expense->approvals()->create(['approved_by' => $approvedBy, 'approved_at' => now()]);

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
