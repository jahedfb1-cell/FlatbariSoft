<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\CashLedger;
use App\Models\JournalEntry;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Every financial mutation in the app goes through this class (BR-23: the
 * user never types debits and credits — modules post automatically).
 * Manual journal entry, when it's built, is the only caller that lets an
 * admin choose accounts directly; everything else calls postJournal() with
 * accounts the service already knows.
 */
class AccountingService
{
    /**
     * BR-02: fiscal year is July-June, e.g. a date in Aug 2026 or Jan 2027
     * is fiscal year "2026-2027". Computed once at post time and stored on
     * the row — never re-derived from entry_date later, so changing this
     * rule in the future can't silently reclassify old entries.
     */
    public function fiscalYearFor(CarbonInterface $date): string
    {
        $year = (int) $date->format('Y');

        return $date->month >= 7 ? "{$year}-".($year + 1) : ($year - 1)."-{$year}";
    }

    /**
     * @param  array<int, array{account_id:int, debit?:float|string, credit?:float|string}>  $lines
     */
    public function postJournal(array $lines, string $narration, string $entryDate, int $createdBy, ?int $approvedBy = null): JournalEntry
    {
        $totalDebit = 0;
        $totalCredit = 0;
        foreach ($lines as $line) {
            $totalDebit = bcadd($totalDebit, (string) ($line['debit'] ?? 0), 4);
            $totalCredit = bcadd($totalCredit, (string) ($line['credit'] ?? 0), 4);
        }

        if (bccomp($totalDebit, $totalCredit, 4) !== 0) {
            throw ValidationException::withMessages([
                'lines' => 'জার্নাল এন্ট্রি ব্যালেন্সড নয় — মোট ডেবিট ('.$totalDebit.') মোট ক্রেডিটের ('.$totalCredit.') সমান হতে হবে।',
            ]);
        }

        return DB::transaction(function () use ($lines, $narration, $entryDate, $createdBy, $approvedBy) {
            $entry = JournalEntry::create([
                'entry_date' => $entryDate,
                'narration' => $narration,
                'fiscal_year' => $this->fiscalYearFor(Carbon::parse($entryDate)),
                'created_by' => $createdBy,
                'approved_by' => $approvedBy,
                'status' => 'POSTED',
            ]);

            foreach ($lines as $line) {
                $entry->lines()->create([
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                ]);
            }

            return $entry->load('lines');
        });
    }

    /**
     * Bank is an asset in our own books: a debit is money coming in
     * (balance up), a credit is money going out (balance down).
     */
    public function postBankTransaction(BankAccount $account, array $data): BankTransaction
    {
        return DB::transaction(function () use ($account, $data) {
            $locked = BankAccount::whereKey($account->id)->lockForUpdate()->first();

            $debit = (string) ($data['debit'] ?? 0);
            $credit = (string) ($data['credit'] ?? 0);
            $newBalance = bcadd((string) $locked->current_balance, bcsub($debit, $credit, 4), 4);

            $txn = $locked->transactions()->create([
                'txn_date' => $data['txn_date'],
                'particulars' => $data['particulars'],
                'debit' => $debit,
                'credit' => $credit,
                'balance_after' => $newBalance,
                'fund_type' => $data['fund_type'],
                'ref_type' => $data['ref_type'] ?? null,
                'ref_id' => $data['ref_id'] ?? null,
                'created_by' => $data['created_by'],
            ]);

            $locked->update(['current_balance' => $newBalance]);

            return $txn;
        });
    }

    /**
     * One global running cash balance (BR-08: bank and cash are two
     * always-separate, always-updated ledgers) — in_amount raises it,
     * out_amount lowers it.
     */
    public function postCashTransaction(array $data): CashLedger
    {
        return DB::transaction(function () use ($data) {
            $last = CashLedger::orderByDesc('id')->lockForUpdate()->first();
            $currentBalance = $last?->balance_after ?? '0';

            $in = (string) ($data['in_amount'] ?? 0);
            $out = (string) ($data['out_amount'] ?? 0);
            $newBalance = bcadd((string) $currentBalance, bcsub($in, $out, 4), 4);

            return CashLedger::create([
                'txn_date' => $data['txn_date'],
                'particulars' => $data['particulars'],
                'in_amount' => $in,
                'out_amount' => $out,
                'balance_after' => $newBalance,
                'fund_type' => $data['fund_type'],
                'ref_type' => $data['ref_type'] ?? null,
                'ref_id' => $data['ref_id'] ?? null,
                'created_by' => $data['created_by'],
            ]);
        });
    }
}
