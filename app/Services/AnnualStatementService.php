<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\BankTransaction;
use App\Models\CashLedger;
use App\Models\ConstructionMaterialPurchase;
use App\Models\Contribution;
use App\Models\Expense;
use App\Models\GasPurchase;
use App\Models\HallBookingPayment;
use App\Models\ParkingPayment;
use App\Models\Payment;
use App\Models\SalaryPayment;
use App\Models\StaffAdvance;
use App\Models\TreasuryEntry;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The year-end statements of the building, for a July-June fiscal year (or up to any date inside it):
 *  1. the cash flow statement - the money that really came in and went out, cash and bank together, from the opening balance to
 *     the closing balance (this is the "receipts and payments" account);
 *  2. the income and expenditure statement (profit & loss) - on the accrual basis: income is what was billed, spending is what
 *     was incurred, whether or not the money has moved yet;
 *  3. the statement of financial position (balance sheet) at the end date.
 * Everything is worked out here from the ledgers and the bills; nothing is stored, and the ledgers are never changed.
 */
class AnnualStatementService
{
    public function __construct(private readonly BillBalanceService $balances) {}

    /** Both ends of a fiscal year, with the end held at today while the year is still running. */
    public function period(int $fiscalYear, ?string $to = null): array
    {
        $from = Carbon::create($fiscalYear, 7, 1, 0, 0, 0, 'Asia/Dhaka');
        $end = Carbon::create($fiscalYear + 1, 6, 30, 0, 0, 0, 'Asia/Dhaka');
        $today = now('Asia/Dhaka')->startOfDay();
        $to = $to ? Carbon::parse($to, 'Asia/Dhaka') : $end;
        $to = $to->greaterThan($end) ? $end : ($to->greaterThan($today) ? $today : $to);

        return [$from->toDateString(), $to->toDateString()];
    }

    /** @return array<string, mixed> */
    public function build(int $fiscalYear, ?string $to = null): array
    {
        [$from, $end] = $this->period($fiscalYear, $to);
        $cash = $this->cashFlow($from, $end);

        return [
            'fiscal_year' => $fiscalYear, 'label' => $fiscalYear.'-'.substr((string) ($fiscalYear + 1), 2), 'from' => $from, 'to' => $end,
            'cash_flow' => $cash,
            'income_expenditure' => $this->incomeExpenditure($from, $end, $cash),
            'balance_sheet' => $this->balanceSheet($end),
        ];
    }

    private function m(string $v): string
    {
        return bcadd($v, '0', 2);
    }

    /** @param  array<string, string>  $bag */
    private function add(array &$bag, string $label, string $amount): void
    {
        $bag[$label] = bcadd($bag[$label] ?? '0', $amount, 4);
    }

    /** @param  array<string, string>  $bag  @return list<array{label:string, amount:string}> */
    private function rows(array $bag, bool $sort = true): array
    {
        $rows = array_map(fn ($l, $a) => ['label' => $l, 'amount' => $this->m($a)], array_keys($bag), $bag);
        $rows = array_values(array_filter($rows, fn ($r) => bccomp($r['amount'], '0', 2) !== 0));
        if ($sort) {
            usort($rows, fn ($a, $b) => bccomp($b['amount'], $a['amount'], 2));
        }

        return $rows;
    }

    // ---- 1. cash flow

    /** Every movement of money (cash and bank, all funds) between the dates, as one list. @return Collection<int, array<string, mixed>> */
    private function movements(string $from, string $to): Collection
    {
        $cash = CashLedger::whereDate('txn_date', '>=', $from)->whereDate('txn_date', '<=', $to)->get()
            ->map(fn ($l) => ['ref_type' => $l->ref_type, 'ref_id' => $l->ref_id, 'fund' => $l->fund_type, 'in' => (string) $l->in_amount, 'out' => (string) $l->out_amount]);
        $bank = BankTransaction::whereDate('txn_date', '>=', $from)->whereDate('txn_date', '<=', $to)->get()
            ->map(fn ($l) => ['ref_type' => $l->ref_type, 'ref_id' => $l->ref_id, 'fund' => $l->fund_type, 'in' => (string) $l->debit, 'out' => (string) $l->credit]);

        return $cash->concat($bank);
    }

    /** The pool of cash and bank (all funds) as it stood at the end of a day. */
    private function pool(string $upTo, bool $inclusive = true): array
    {
        $op = $inclusive ? '<=' : '<';
        $cash = bcsub((string) (CashLedger::whereDate('txn_date', $op, $upTo)->sum('in_amount') ?: 0), (string) (CashLedger::whereDate('txn_date', $op, $upTo)->sum('out_amount') ?: 0), 4);
        $bank = bcsub((string) (BankTransaction::whereDate('txn_date', $op, $upTo)->sum('debit') ?: 0), (string) (BankTransaction::whereDate('txn_date', $op, $upTo)->sum('credit') ?: 0), 4);

        return ['cash' => $cash, 'bank' => $bank, 'total' => bcadd($cash, $bank, 4)];
    }

    /** @return array<string, mixed> */
    public function cashFlow(string $from, string $to): array
    {
        $before = $this->pool($from, false);
        $lines = $this->movements($from, $to);

        $expenses = Expense::with('expenseHead')->whereIn('id', $lines->where('ref_type', Expense::class)->pluck('ref_id'))->get()->keyBy('id');
        $treasury = TreasuryEntry::whereIn('id', $lines->where('ref_type', TreasuryEntry::class)->pluck('ref_id'))->pluck('kind', 'id');
        $books = DB::table('contributions as c')->join('contribution_heads as h', 'h.id', '=', 'c.contribution_head_id')->join('contribution_books as b', 'b.id', '=', 'h.contribution_book_id')
            ->whereIn('c.id', $lines->where('ref_type', Contribution::class)->pluck('ref_id'))->pluck('b.name_bn', 'c.id');

        $receipts = [];
        $payments = [];
        $openingEntered = '0';
        foreach ($lines as $l) {
            $in = $l['in'];
            $out = $l['out'];
            switch ($l['ref_type']) {
                case Payment::class:
                    $label = $l['fund'] === 'GAS' ? 'গ্যাস বিল আদায়' : 'সার্ভিস চার্জ আদায়';
                    $this->add($receipts, $label, bcsub($in, $out, 4));
                    break;
                case Contribution::class:
                    $this->add($receipts, 'বিশেষ আদায়: '.($books[$l['ref_id']] ?? 'অন্যান্য'), bcsub($in, $out, 4));
                    break;
                case HallBookingPayment::class:
                    $this->add($receipts, 'কমিউনিটি হল ভাড়া', bcsub($in, $out, 4));
                    break;
                case ParkingPayment::class:
                    $this->add($receipts, 'পার্কিং আদায়', bcsub($in, $out, 4));
                    break;
                case Expense::class:
                    $e = $expenses[$l['ref_id']] ?? null;
                    $this->add($payments, $e?->expenseHead?->name_bn ?? 'অন্যান্য খরচ', bcsub($out, $in, 4));
                    break;
                case TreasuryEntry::class:
                    match ($treasury[$l['ref_id']] ?? '') {
                        'OPENING' => $openingEntered = bcadd($openingEntered, bcsub($in, $out, 4), 4), // brought in as the starting balance
                        'BANK_PROFIT' => $this->add($receipts, 'ব্যাংক মুনাফা', bcsub($in, $out, 4)),
                        default => null, // cash <-> bank and fund <-> fund moves: the pool is unchanged
                    };
                    break;
                default:
                    $this->add($receipts, 'অন্যান্য প্রাপ্তি', $in);
                    $this->add($payments, 'অন্যান্য প্রদান', $out);
            }
        }

        $opening = bcadd($before['total'], $openingEntered, 4);
        $totalReceipts = array_reduce($receipts, fn ($c, $v) => bcadd($c, $v, 4), '0');
        $totalPayments = array_reduce($payments, fn ($c, $v) => bcadd($c, $v, 4), '0');
        $closing = bcadd(bcsub($opening, '0', 4), bcsub($totalReceipts, $totalPayments, 4), 4);
        $ledgerEnd = $this->pool($to);

        return [
            'opening' => $this->m($opening),
            'receipts' => $this->rows($receipts), 'total_receipts' => $this->m($totalReceipts),
            'payments' => $this->rows($payments), 'total_payments' => $this->m($totalPayments),
            'net' => $this->m(bcsub($totalReceipts, $totalPayments, 4)),
            'closing' => $this->m($closing), 'closing_cash' => $this->m($ledgerEnd['cash']), 'closing_bank' => $this->m($ledgerEnd['bank']),
            // the closing balance worked out from the flows must be the same money the cash and bank books hold
            'agrees_with_books' => bccomp($closing, $ledgerEnd['total'], 2) === 0,
        ];
    }

    // ---- 2. income and expenditure (profit & loss)

    /** @param  array<string, mixed>  $cash @return array<string, mixed> */
    public function incomeExpenditure(string $from, string $to, array $cash): array
    {
        $fromKey = (int) Carbon::parse($from)->format('Ym');
        $toKey = (int) Carbon::parse($to)->format('Ym');

        $billed = Bill::with('billingPeriod')->get()->filter(fn (Bill $b) => $b->billingPeriod && ($k = $b->billingPeriod->year * 100 + $b->billingPeriod->month) >= $fromKey && $k <= $toKey);
        $sum = fn (string $col) => $billed->reduce(fn ($c, $b) => bcadd($c, (string) $b->{$col}, 4), '0');

        $income = [];
        $this->add($income, 'সার্ভিস চার্জ (বিল করা)', $sum('service_charge'));
        $this->add($income, 'গ্যাস বিল (বিল করা)', $sum('gas_amount'));
        $this->add($income, 'বিলম্ব ফি ও অন্যান্য চার্জ (বিল করা)', bcadd($sum('late_fee'), $sum('other_charges'), 4));
        foreach (['কমিউনিটি হল ভাড়া', 'পার্কিং আদায়', 'ব্যাংক মুনাফা'] as $label) {
            $hit = collect($cash['receipts'])->firstWhere('label', $label);
            if ($hit) {
                $this->add($income, $label, $hit['amount']);
            }
        }
        $totalIncome = array_reduce($income, fn ($c, $v) => bcadd($c, $v, 4), '0');

        // spending: what was paid out (an expense is entered when it is approved and paid) plus gas / material bought but not yet paid for
        $operating = [];
        $capital = [];
        $approved = Expense::with('expenseHead')->where('status', 'APPROVED')->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)->get();
        foreach ($approved as $e) {
            $isCapital = $e->fund_type === 'CAPITAL' || $e->work_budget_id;
            if ($isCapital) {
                $this->add($capital, $e->expenseHead?->name_bn ?? 'অন্যান্য খরচ', (string) $e->amount);
            } else {
                $this->add($operating, $e->expenseHead?->name_bn ?? 'অন্যান্য খরচ', (string) $e->amount);
            }
        }
        foreach ([[GasPurchase::class, 'গ্যাস ক্রয় (এখনো পরিশোধ হয়নি)'], [ConstructionMaterialPurchase::class, 'নির্মাণ সামগ্রী (এখনো পরিশোধ হয়নি)']] as [$model, $label]) {
            $unpaid = $model::with('expense')->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)->get()->filter(fn ($p) => $p->expense?->status !== 'APPROVED');
            $isCapital = $model === ConstructionMaterialPurchase::class;
            $sumUnpaid = $unpaid->reduce(fn ($c, $p) => bcadd($c, (string) $p->total_cost, 4), '0');
            if ($isCapital) {
                $this->add($capital, $label, $sumUnpaid);
            } else {
                $this->add($operating, $label, $sumUnpaid);
            }
        }
        $totalOperating = array_reduce($operating, fn ($c, $v) => bcadd($c, $v, 4), '0');
        $totalCapital = array_reduce($capital, fn ($c, $v) => bcadd($c, $v, 4), '0');

        $collected = '0';
        foreach ($cash['receipts'] as $r) {
            if (in_array($r['label'], ['সার্ভিস চার্জ আদায়', 'গ্যাস বিল আদায়'], true)) {
                $collected = bcadd($collected, $r['amount'], 2);
            }
        }
        $billedTotal = bcadd(bcadd($sum('service_charge'), $sum('gas_amount'), 4), bcadd($sum('late_fee'), $sum('other_charges'), 4), 4);

        return [
            'income' => $this->rows($income), 'total_income' => $this->m($totalIncome),
            'expenditure' => $this->rows($operating), 'total_expenditure' => $this->m($totalOperating),
            'surplus' => $this->m(bcsub($totalIncome, $totalOperating, 4)),
            'capital_spending' => $this->rows($capital), 'total_capital_spending' => $this->m($totalCapital),
            'special_collections' => collect($cash['receipts'])->filter(fn ($r) => str_starts_with($r['label'], 'বিশেষ আদায়'))->values()->all(),
            'memo' => [
                'billed' => $this->m($billedTotal), 'collected' => $this->m($collected),
                'collection_percent' => bccomp($billedTotal, '0', 4) > 0 ? round((float) bcmul(bcdiv($collected, $billedTotal, 6), '100', 4), 1) : null,
            ],
        ];
    }

    // ---- 3. financial position (balance sheet)

    /** @return array<string, mixed> */
    public function balanceSheet(string $asOf): array
    {
        $pool = $this->pool($asOf);

        // flats' dues at that date: each flat's latest bill up to that month, less the payments received for it by that date
        $y = (int) Carbon::parse($asOf)->format('Y');
        $mo = (int) Carbon::parse($asOf)->format('n');
        $receivable = '0';
        $gasReceivable = '0';
        foreach ($this->balances->latestPerFlat($y, $mo) as $b) {
            $paid = (string) (Payment::where('bill_id', $b->id)->where('status', 'APPROVED')->whereDate('payment_date', '<=', $asOf)->sum('amount') ?: 0);
            $due = bcsub((string) $b->total_payable, $paid, 4);
            if (bccomp($due, '0', 4) > 0) {
                $receivable = bcadd($receivable, $due, 4);
                if (bccomp((string) $b->total_payable, '0', 4) > 0) {
                    $gasReceivable = bcadd($gasReceivable, bcdiv(bcmul($due, (string) $b->gas_amount, 8), (string) $b->total_payable, 4), 4);
                }
            }
        }

        // advances given to staff and not yet taken back
        $advances = '0';
        if (Schema::hasTable('staff_advances') && Schema::hasColumn('salary_payments', 'expense_id')) {
            $given = (string) (StaffAdvance::where('given_date', '<=', $asOf)->whereHas('expense', fn ($q) => $q->where('status', 'APPROVED'))->sum('amount') ?: 0);
            $taken = (string) (SalaryPayment::whereNotNull('expense_id')->whereDate('paid_date', '<=', $asOf)->sum('advance_deduction') ?: 0);
            $advances = bccomp(bcsub($given, $taken, 4), '0', 4) > 0 ? bcsub($given, $taken, 4) : '0';
        }

        // what is owed to gas and material suppliers: bought up to that date, not yet paid
        $suppliers = '0';
        foreach ([GasPurchase::class, ConstructionMaterialPurchase::class] as $model) {
            $suppliers = $model::with('expense')->whereDate('date', '<=', $asOf)->get()
                ->filter(fn ($p) => $p->expense?->status !== 'APPROVED')->reduce(fn ($c, $p) => bcadd($c, (string) $p->total_cost, 4), $suppliers);
        }

        $assets = [
            ['label' => 'হাতে নগদ', 'amount' => $this->m($pool['cash'])],
            ['label' => 'ব্যাংকে জমা', 'amount' => $this->m($pool['bank'])],
            ['label' => 'ফ্ল্যাটদের কাছে বকেয়া পাওনা', 'amount' => $this->m($receivable)],
            ['label' => 'স্টাফের কাছে অগ্রিম', 'amount' => $this->m($advances)],
        ];
        $totalAssets = bcadd(bcadd($pool['total'], $receivable, 4), $advances, 4);
        $liabilities = [['label' => 'সরবরাহকারীর কাছে দেনা (গ্যাস, নির্মাণ সামগ্রী)', 'amount' => $this->m($suppliers)]];

        // each fund's own money (cash + bank), then what the building is owed, net of what it owes
        $funds = [];
        $names = ['OPERATING' => 'সার্ভিস চার্জ তহবিল', 'GAS' => 'গ্যাস তহবিল', 'CAPITAL' => 'প্রজেক্ট (মূলধন) তহবিল', 'SINKING' => 'সঞ্চিত তহবিল'];
        foreach ($names as $code => $label) {
            $cashIn = bcsub((string) (CashLedger::where('fund_type', $code)->whereDate('txn_date', '<=', $asOf)->sum('in_amount') ?: 0), (string) (CashLedger::where('fund_type', $code)->whereDate('txn_date', '<=', $asOf)->sum('out_amount') ?: 0), 4);
            $bankIn = bcsub((string) (BankTransaction::where('fund_type', $code)->whereDate('txn_date', '<=', $asOf)->sum('debit') ?: 0), (string) (BankTransaction::where('fund_type', $code)->whereDate('txn_date', '<=', $asOf)->sum('credit') ?: 0), 4);
            $funds[] = ['label' => $label, 'amount' => $this->m(bcadd($cashIn, $bankIn, 4))];
        }
        $fundTotal = array_reduce($funds, fn ($c, $f) => bcadd($c, $f['amount'], 4), '0');
        $totalLiabilities = $suppliers;
        $accrued = bcsub(bcsub($totalAssets, $totalLiabilities, 4), $fundTotal, 4); // owed to us (dues, advances) less owed by us

        return [
            'as_of' => $asOf, 'assets' => $assets, 'total_assets' => $this->m($totalAssets),
            'liabilities' => $liabilities, 'total_liabilities' => $this->m($totalLiabilities),
            'funds' => $funds, 'accrued' => ['label' => 'বকেয়া পাওনা ও অগ্রিম (নিট, দেনা বাদে)', 'amount' => $this->m($accrued)],
            'total_funds' => $this->m(bcadd($fundTotal, $accrued, 4)),
            'total_liabilities_and_funds' => $this->m(bcadd($totalLiabilities, bcadd($fundTotal, $accrued, 4), 4)),
            'balanced' => bccomp($totalAssets, bcadd($totalLiabilities, bcadd($fundTotal, $accrued, 4), 4), 2) === 0,
            'gas_receivable' => $this->m($gasReceivable),
        ];
    }
}
