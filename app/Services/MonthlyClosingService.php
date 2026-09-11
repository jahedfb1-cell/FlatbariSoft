<?php

namespace App\Services;

use App\Models\BillingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Expense;
use App\Models\FundTransfer;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Confirmed rule #8: collection - total expense = surplus, transferred to
 * the Sinking Fund automatically at month end. Locks the billing period
 * (BR-18/rule #18: a LOCKED period's entries can't be edited/deleted
 * except by Super Admin unlocking it — enforced elsewhere, this service
 * only sets the flag).
 */
class MonthlyClosingService
{
    public function __construct(private readonly AccountingService $accounting) {}

    public function close(int $year, int $month, int $closedBy): ?FundTransfer
    {
        $period = BillingPeriod::where('year', $year)->where('month', $month)->firstOrFail();

        if ($period->status === 'LOCKED') {
            throw ValidationException::withMessages(['period' => 'এই মাস ইতিমধ্যে ক্লোজ করা হয়েছে।']);
        }

        return DB::transaction(function () use ($period, $year, $month, $closedBy) {
            $collection = Payment::where('status', 'APPROVED')
                ->whereYear('payment_date', $year)->whereMonth('payment_date', $month)
                ->sum('amount');

            $expense = Expense::where('status', 'APPROVED')
                ->whereYear('date', $year)->whereMonth('date', $month)
                ->sum('amount');

            $surplus = bcsub((string) $collection, (string) $expense, 4);

            $transfer = null;

            if (bccomp($surplus, '0', 4) > 0) {
                $transfer = FundTransfer::create([
                    'from_fund' => 'OPERATING',
                    'to_fund' => 'SINKING',
                    'amount' => $surplus,
                    'reason' => "মাসিক ক্লোজিং উদ্বৃত্ত {$year}-{$month}",
                    'transfer_date' => now()->toDateString(),
                    'created_by' => $closedBy,
                    'approved_by' => $closedBy,
                ]);

                $operatingFund = ChartOfAccount::where('code', '3100')->firstOrFail();
                $sinkingFund = ChartOfAccount::where('code', '3300')->firstOrFail();

                $this->accounting->postJournal(
                    [
                        ['account_id' => $operatingFund->id, 'debit' => $surplus],
                        ['account_id' => $sinkingFund->id, 'credit' => $surplus],
                    ],
                    "মাসিক ক্লোজিং উদ্বৃত্ত ট্রান্সফার {$year}-{$month}",
                    now()->toDateString(),
                    $closedBy,
                    $closedBy,
                );
            }

            $period->update(['status' => 'LOCKED', 'locked_at' => now()]);

            return $transfer;
        });
    }
}
