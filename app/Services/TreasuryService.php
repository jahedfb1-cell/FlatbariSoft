<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\CashLedger;
use App\Models\ChartOfAccount;
use App\Models\TreasuryEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The money movements no other module creates, so the monthly statements can be complete:
 *
 *   OPENING             cash / bank a fund already held when the system started
 *   DEPOSIT_TO_BANK     cash put into the bank          (cash -> bank, same fund)
 *   WITHDRAW_FROM_BANK  cash taken out of the bank      (bank -> cash, same fund)
 *   BANK_PROFIT         interest / profit the bank paid
 *   FUND_LOAN           one fund lends to another       (service charge -> gas, -> project, ...)
 *   LOAN_REPAYMENT      the borrowing fund pays back
 *
 * Every entry writes its cash/bank rows and a balanced journal entry atomically. Entries are never edited
 * or deleted; reverse() adds an opposite entry. A fund can never spend or lend more cash (or bank money)
 * than it holds, and a repayment can never exceed what is still owed.
 */
class TreasuryService
{
    private const FUNDS = ['OPERATING', 'GAS', 'CAPITAL', 'SINKING'];

    /** How each kind moves money: [medium, which fund, direction] per ledger row, and the journal's debit/credit accounts. */
    private function spec(string $kind, ?string $medium): array
    {
        return match ($kind) {
            'OPENING' => ['rows' => [[$medium, 'fund', 'IN']], 'debit' => $medium === 'CASH' ? '1200' : '1100', 'credit' => '3900'],
            'DEPOSIT_TO_BANK' => ['rows' => [['CASH', 'fund', 'OUT'], ['BANK', 'fund', 'IN']], 'debit' => '1100', 'credit' => '1200'],
            'WITHDRAW_FROM_BANK' => ['rows' => [['BANK', 'fund', 'OUT'], ['CASH', 'fund', 'IN']], 'debit' => '1200', 'credit' => '1100'],
            'BANK_PROFIT' => ['rows' => [['BANK', 'fund', 'IN']], 'debit' => '1100', 'credit' => '4900'],
            'FUND_LOAN' => ['rows' => [[$medium, 'fund', 'OUT'], [$medium, 'to_fund', 'IN']], 'debit' => '1400', 'credit' => '2400'],
            'LOAN_REPAYMENT' => ['rows' => [[$medium, 'fund', 'OUT'], [$medium, 'to_fund', 'IN']], 'debit' => '2400', 'credit' => '1400'],
        };
    }

    public function __construct(private readonly AccountingService $accounting) {}

    // ---- the operations

    /** A fund's cash and bank balances on the day the system starts (either may be 0). */
    public function openingBalance(string $fund, string $cash, string $bank, string $date, ?string $notes, int $by): array
    {
        $this->assertFund($fund);

        return DB::transaction(function () use ($fund, $cash, $bank, $date, $notes, $by) {
            $made = [];
            foreach ([['CASH', $cash], ['BANK', $bank]] as [$medium, $amount]) {
                if (bccomp($amount, '0', 4) > 0) {
                    $made[] = $this->create('OPENING', $fund, null, $medium, $amount, $date, $notes, $by);
                }
            }
            if ($made === []) {
                throw ValidationException::withMessages(['cash' => 'ক্যাশ বা ব্যাংকের অন্তত একটার পরিমাণ শূন্যের বেশি হতে হবে।']);
            }

            return $made;
        });
    }

    public function depositToBank(string $fund, string $amount, string $date, ?string $notes, int $by): TreasuryEntry
    {
        $this->assertFund($fund);

        return DB::transaction(fn () => $this->create('DEPOSIT_TO_BANK', $fund, null, null, $amount, $date, $notes, $by));
    }

    public function withdrawFromBank(string $fund, string $amount, string $date, ?string $notes, int $by): TreasuryEntry
    {
        $this->assertFund($fund);

        return DB::transaction(fn () => $this->create('WITHDRAW_FROM_BANK', $fund, null, null, $amount, $date, $notes, $by));
    }

    public function bankProfit(string $fund, string $amount, string $date, ?string $notes, int $by): TreasuryEntry
    {
        $this->assertFund($fund);

        return DB::transaction(fn () => $this->create('BANK_PROFIT', $fund, null, null, $amount, $date, $notes, $by));
    }

    public function lend(string $from, string $to, string $medium, string $amount, string $date, ?string $notes, int $by): TreasuryEntry
    {
        $this->assertPair($from, $to);

        return DB::transaction(fn () => $this->create('FUND_LOAN', $from, $to, $medium, $amount, $date, $notes, $by));
    }

    /** $borrower pays $lender back. */
    public function repay(string $borrower, string $lender, string $medium, string $amount, string $date, ?string $notes, int $by): TreasuryEntry
    {
        $this->assertPair($borrower, $lender);

        return DB::transaction(function () use ($borrower, $lender, $medium, $amount, $date, $notes, $by) {
            $owed = $this->owed($borrower, $lender);
            if (bccomp($amount, $owed, 4) > 0) {
                throw ValidationException::withMessages(['amount' => 'শোধের পরিমাণ বকেয়া ধারের ('.$owed.') বেশি হতে পারে না।']);
            }

            return $this->create('LOAN_REPAYMENT', $borrower, $lender, $medium, $amount, $date, $notes, $by);
        });
    }

    /** Undoes an entry with an opposite one. The original is never touched. */
    public function reverse(TreasuryEntry $entry, string $reason, int $by): TreasuryEntry
    {
        return DB::transaction(function () use ($entry, $reason, $by) {
            $original = TreasuryEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();

            if ($original->reverses_id !== null) {
                throw ValidationException::withMessages(['entry' => 'এটি আগেই বাতিলের এন্ট্রি।']);
            }
            if (TreasuryEntry::where('reverses_id', $original->id)->exists()) {
                throw ValidationException::withMessages(['entry' => 'এই এন্ট্রি ইতিমধ্যে বাতিল করা হয়েছে।']);
            }
            if ($original->kind === 'FUND_LOAN' && bccomp($this->owed($original->to_fund, $original->fund), (string) $original->amount, 4) < 0) {
                throw ValidationException::withMessages(['entry' => 'এই ধারের কিছু অংশ আগেই শোধ হয়েছে — আগে শোধ বাতিল করুন।']);
            }

            $row = TreasuryEntry::create([
                'kind' => $original->kind, 'fund' => $original->fund, 'to_fund' => $original->to_fund, 'medium' => $original->medium,
                // a wrong opening balance is corrected in the month it was entered for; everything else is undone today
                'amount' => $original->amount,
                'entry_date' => $original->kind === 'OPENING' ? $original->entry_date->toDateString() : Carbon::now('Asia/Dhaka')->toDateString(),
                'notes' => "বাতিল: {$reason} ({$original->receipt_no})", 'reverses_id' => $original->id, 'created_by' => $by,
            ]);
            $row->update(['receipt_no' => 'TR-'.str_pad((string) $row->id, 6, '0', STR_PAD_LEFT)]);
            $this->post($row, flip: true);

            return $row->fresh();
        });
    }

    // ---- balances

    /** Cash a fund holds, counting everything on or before $upTo (all time when null). */
    public function cashBalance(string $fund, ?string $upTo = null): string
    {
        $q = CashLedger::where('fund_type', $fund)->when($upTo, fn ($w) => $w->where('txn_date', '<=', $upTo));

        return bcsub((string) ($q->sum('in_amount') ?: 0), (string) ($q->sum('out_amount') ?: 0), 4);
    }

    public function bankBalance(string $fund, ?string $upTo = null): string
    {
        $q = BankTransaction::where('fund_type', $fund)->when($upTo, fn ($w) => $w->where('txn_date', '<=', $upTo));

        return bcsub((string) ($q->sum('debit') ?: 0), (string) ($q->sum('credit') ?: 0), 4);
    }

    /** What $borrower still owes $lender: all loans minus all repayments, reversals netted out. */
    public function owed(string $borrower, string $lender): string
    {
        $net = function (string $kind, string $from, string $to) {
            $rows = TreasuryEntry::where('kind', $kind)->where('fund', $from)->where('to_fund', $to)->get();
            $orig = $rows->whereNull('reverses_id')->sum('amount');
            $rev = $rows->whereNotNull('reverses_id')->sum('amount');

            return bcsub((string) $orig, (string) $rev, 4);
        };

        // the loan row is stored lender -> borrower; the repayment row borrower -> lender
        return bcsub($net('FUND_LOAN', $lender, $borrower), $net('LOAN_REPAYMENT', $borrower, $lender), 4);
    }

    /** @return list<array{lender:string, borrower:string, owed:string}> only the pairs that still owe something */
    public function loanPositions(): array
    {
        $out = [];
        foreach (self::FUNDS as $lender) {
            foreach (self::FUNDS as $borrower) {
                if ($lender !== $borrower && bccomp($owed = $this->owed($borrower, $lender), '0', 4) > 0) {
                    $out[] = ['lender' => $lender, 'borrower' => $borrower, 'owed' => $owed];
                }
            }
        }

        return $out;
    }

    // ---- internals

    private function create(string $kind, string $fund, ?string $toFund, ?string $medium, string $amount, string $date, ?string $notes, int $by): TreasuryEntry
    {
        if (bccomp($amount, '0', 4) <= 0) {
            throw ValidationException::withMessages(['amount' => 'পরিমাণ শূন্যের বেশি হতে হবে।']);
        }

        $row = TreasuryEntry::create([
            'kind' => $kind, 'fund' => $fund, 'to_fund' => $toFund, 'medium' => $medium, 'amount' => $amount,
            'entry_date' => $date, 'notes' => $notes, 'created_by' => $by,
        ]);
        $row->update(['receipt_no' => 'TR-'.str_pad((string) $row->id, 6, '0', STR_PAD_LEFT)]);
        $this->post($row, flip: false);

        return $row->fresh();
    }

    /** Writes the cash/bank rows and the journal entry for an entry (or its opposite when $flip). */
    private function post(TreasuryEntry $row, bool $flip): void
    {
        $spec = $this->spec($row->kind, $row->medium);
        $amount = bcadd((string) $row->amount, '0', 4);
        $date = $row->entry_date->toDateString();
        $label = $this->label($row);

        // a fund can never spend more than it holds (a reversal included) - checked before anything is written
        foreach ($spec['rows'] as [$medium, $which, $dir]) {
            if (($dir === 'OUT') !== $flip) {
                $held = $medium === 'CASH' ? $this->cashBalance($row->{$which}) : $this->bankBalance($row->{$which});
                if (bccomp($amount, $held, 4) > 0) {
                    throw ValidationException::withMessages(['amount' => ($medium === 'CASH' ? 'ক্যাশে' : 'ব্যাংকে').' এই তহবিলের যথেষ্ট টাকা নেই (আছে '.$held.')।']);
                }
            }
        }

        $bank = null;
        foreach ($spec['rows'] as [$medium, $which, $dir]) {
            $in = ($dir === 'IN') !== $flip; // flipped entries go the other way
            $base = ['txn_date' => $date, 'particulars' => $label, 'fund_type' => $row->{$which}, 'ref_type' => TreasuryEntry::class, 'ref_id' => $row->id, 'created_by' => $row->created_by];

            if ($medium === 'CASH') {
                $this->accounting->postCashTransaction($base + ($in ? ['in_amount' => $amount] : ['out_amount' => $amount]));
            } else {
                $bank ??= BankAccount::first() ?? throw ValidationException::withMessages(['bank' => 'কোনো ব্যাংক হিসাব সেটআপ করা নেই।']);
                $this->accounting->postBankTransaction($bank, $base + ($in ? ['debit' => $amount] : ['credit' => $amount]));
            }
        }

        $debit = ChartOfAccount::where('code', $flip ? $spec['credit'] : $spec['debit'])->firstOrFail();
        $credit = ChartOfAccount::where('code', $flip ? $spec['debit'] : $spec['credit'])->firstOrFail();
        $entry = $this->accounting->postJournal(
            [['account_id' => $debit->id, 'debit' => $amount], ['account_id' => $credit->id, 'credit' => $amount]],
            $label, $date, $row->created_by, $row->created_by,
        );
        $row->update(['journal_entry_id' => $entry->id]);
    }

    private function label(TreasuryEntry $row): string
    {
        $funds = config('funds.funds');
        $to = $funds[$row->to_fund]['bn'] ?? '';
        $base = match ($row->kind) {
            'OPENING' => 'শুরুর ব্যালেন্স ('.($row->medium === 'CASH' ? 'ক্যাশ' : 'ব্যাংক').')',
            'DEPOSIT_TO_BANK' => 'ক্যাশ ব্যাংকে জমা',
            'WITHDRAW_FROM_BANK' => 'ব্যাংক থেকে ক্যাশ উত্তোলন',
            'BANK_PROFIT' => 'ব্যাংক মুনাফা',
            'FUND_LOAN' => "ধার প্রদান — {$to}",
            'LOAN_REPAYMENT' => "ধার শোধ — {$to}",
        };

        return $base.($row->reverses_id ? ' (বাতিল)' : '').($row->notes ? " — {$row->notes}" : '')." ({$row->receipt_no})";
    }

    private function assertFund(string $fund): void
    {
        if (! in_array($fund, self::FUNDS, true)) {
            throw ValidationException::withMessages(['fund' => 'অজানা তহবিল।']);
        }
    }

    private function assertPair(string $a, string $b): void
    {
        $this->assertFund($a);
        $this->assertFund($b);
        if ($a === $b) {
            throw ValidationException::withMessages(['to_fund' => 'একই তহবিলে ধার দেওয়া যায় না।']);
        }
    }
}
