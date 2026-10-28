<?php

namespace App\Services;

use App\Models\BankTransaction;
use App\Models\CashLedger;
use App\Models\Contribution;
use App\Models\Expense;
use App\Models\GasPurchase;
use App\Models\HallBookingPayment;
use App\Models\ParkingPayment;
use App\Models\Payment;
use App\Models\TreasuryEntry;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The monthly Receipts & Payments statement of one fund (service charge, gas, project, sinking), built only
 * from the cash and bank ledgers - nothing is typed in by hand.
 *
 *   Receipts side :  opening balance (cash + bank)  +  every receipt of the month
 *   Payments side :  every payment of the month     +  closing balance (cash + bank)
 *
 * and the two sides always total the same. Cash put into the bank, or taken out of it, is not a receipt or a
 * payment: it only moves money between the fund's own cash and bank, so it is shown in the bank note instead.
 * Each entry appears in the month of its own date. Receipts and payments are also summed per head, so a month
 * can be read in one glance before going down the dated lines.
 */
class FundStatementService
{
    public function __construct(private readonly TreasuryService $treasury) {}

    public function statement(string $fund, int $year, int $month): array
    {
        $funds = config('funds.funds');
        if (! isset($funds[$fund])) {
            throw ValidationException::withMessages(['fund' => 'অজানা তহবিল।']);
        }

        $start = Carbon::create($year, $month, 1)->startOfDay();
        $from = $start->toDateString();
        $to = $start->copy()->endOfMonth()->toDateString();

        $items = $this->items($fund, $to);
        $openingCash = '0';
        $openingBank = '0';
        $receipts = [];
        $payments = [];
        $bank = ['in_cash_deposits' => '0', 'in_other' => '0', 'profit' => '0', 'out_withdrawn' => '0', 'out_payments' => '0'];

        foreach ($items as $it) {
            $signed = $it['dir'] === 'IN' ? $it['amount'] : bcmul($it['amount'], '-1', 4);
            $before = $it['date'] < $from;

            if ($before || $it['opening']) { // anything earlier, and opening balances entered for this very month
                if ($it['medium'] === 'CASH') {
                    $openingCash = bcadd($openingCash, $signed, 4);
                } else {
                    $openingBank = bcadd($openingBank, $signed, 4);
                }

                continue;
            }

            // from here the row is inside the month
            if ($it['medium'] === 'BANK') {
                $key = $it['contra'] ? ($it['dir'] === 'IN' ? 'in_cash_deposits' : 'out_withdrawn') : ($it['head']['code'] === 'BANK_PROFIT' ? 'profit' : ($it['dir'] === 'IN' ? 'in_other' : 'out_payments'));
                $bank[$key] = bcadd($bank[$key], $it['amount'], 4);
            }
            if ($it['contra']) {
                continue;
            }
            $it['dir'] === 'IN' ? $receipts[] = $it : $payments[] = $it;
        }

        $closingCash = bcadd($openingCash, $this->sumFlow($items, $from, $to, 'CASH'), 4);
        $closingBank = bcadd($openingBank, $this->sumFlow($items, $from, $to, 'BANK'), 4);

        $receiptsTotal = $this->total($receipts);
        $paymentsTotal = $this->total($payments);
        $openingTotal = bcadd($openingCash, $openingBank, 4);
        $closingTotal = bcadd($closingCash, $closingBank, 4);

        return [
            'fund' => ['code' => $fund, 'name_bn' => $funds[$fund]['bn'], 'name_en' => $funds[$fund]['en']],
            'year' => $year,
            'month' => $month,
            'from' => $from,
            'to' => $to,
            'opening' => ['cash' => $openingCash, 'bank' => $openingBank, 'total' => $openingTotal],
            'receipts' => $this->lines($receipts),
            'payments' => $this->lines($payments),
            'receipts_by_head' => $this->byHead($receipts),
            'payments_by_head' => $this->byHead($payments),
            'totals' => ['receipts' => $receiptsTotal, 'payments' => $paymentsTotal],
            'closing' => ['cash' => $closingCash, 'bank' => $closingBank, 'total' => $closingTotal],
            // the two sides of the account; equal by construction
            'left_total' => bcadd($openingTotal, $receiptsTotal, 4),
            'right_total' => bcadd($paymentsTotal, $closingTotal, 4),
            'bank_note' => [
                'opening' => $openingBank,
                'cash_deposited' => $bank['in_cash_deposits'],
                'other_receipts' => $bank['in_other'],
                'profit' => $bank['profit'],
                'withdrawn' => $bank['out_withdrawn'],
                'payments' => $bank['out_payments'],
                'closing' => $closingBank,
            ],
            'loans' => $this->loansFor($fund),
        ];
    }

    /** All four funds side by side, and who owes whom between them. */
    public function summary(int $year, int $month): array
    {
        $rows = [];
        $sum = ['opening' => '0', 'receipts' => '0', 'payments' => '0', 'closing' => '0'];
        foreach (array_keys(config('funds.funds')) as $fund) {
            $s = $this->statement($fund, $year, $month);
            $rows[] = [
                'fund' => $s['fund'],
                'opening' => $s['opening']['total'], 'receipts' => $s['totals']['receipts'],
                'payments' => $s['totals']['payments'], 'closing' => $s['closing']['total'],
                'closing_cash' => $s['closing']['cash'], 'closing_bank' => $s['closing']['bank'],
            ];
            foreach (['opening' => $s['opening']['total'], 'receipts' => $s['totals']['receipts'], 'payments' => $s['totals']['payments'], 'closing' => $s['closing']['total']] as $k => $v) {
                $sum[$k] = bcadd($sum[$k], $v, 4);
            }
        }

        return ['year' => $year, 'month' => $month, 'funds' => $rows, 'total' => $sum, 'loans' => $this->loansFor(null)];
    }

    // ---- internals

    /** Every cash and bank row of the fund up to the end of the month, classified. */
    private function items(string $fund, string $to): Collection
    {
        $cash = CashLedger::where('fund_type', $fund)->where('txn_date', '<=', $to)->get()
            ->map(fn ($r) => ['row' => $r, 'medium' => 'CASH', 'dir' => bccomp((string) $r->in_amount, '0', 4) > 0 ? 'IN' : 'OUT', 'amount' => bcadd(bccomp((string) $r->in_amount, '0', 4) > 0 ? (string) $r->in_amount : (string) $r->out_amount, '0', 4)]);
        $bank = BankTransaction::where('fund_type', $fund)->where('txn_date', '<=', $to)->get()
            ->map(fn ($r) => ['row' => $r, 'medium' => 'BANK', 'dir' => bccomp((string) $r->debit, '0', 4) > 0 ? 'IN' : 'OUT', 'amount' => bcadd(bccomp((string) $r->debit, '0', 4) > 0 ? (string) $r->debit : (string) $r->credit, '0', 4)]);
        $all = $cash->concat($bank);

        $ctx = $this->context($all);

        return $all->map(fn ($x) => $this->classify($x, $fund, $ctx))
            ->sortBy(fn ($i) => $i['date'].sprintf('%012d', $i['id']))->values();
    }

    /** Loads, in a few queries, everything the rows refer to. */
    private function context(Collection $all): array
    {
        $ids = fn (string $class) => $all->filter(fn ($x) => $x['row']->ref_type === $class)->pluck('row.ref_id')->unique()->values();

        $expenses = Expense::with('expenseHead')->whereIn('id', $ids(Expense::class))->get()->keyBy('id');

        return [
            'expenses' => $expenses,
            'gas' => GasPurchase::whereIn('expense_id', $expenses->keys())->get()->keyBy('expense_id'),
            'treasury' => TreasuryEntry::whereIn('id', $ids(TreasuryEntry::class))->get()->keyBy('id'),
            'hall' => HallBookingPayment::whereIn('id', $ids(HallBookingPayment::class))->get()->keyBy('id'),
            'contributions' => Contribution::with('head.book')->whereIn('id', $ids(Contribution::class))->get()->keyBy('id'),
        ];
    }

    private function classify(array $x, string $fund, array $ctx): array
    {
        $r = $x['row'];
        $dir = $x['dir'];
        $head = ['code' => 'OTHER', 'name_bn' => 'অন্যান্য', 'name_en' => 'Other'];
        $contra = false;
        $opening = false;
        $particulars = $r->particulars;

        switch ($r->ref_type) {
            case Payment::class:
                $head = $fund === 'GAS'
                    ? ['code' => 'GAS_BILL', 'name_bn' => 'ফ্ল্যাট মালিকদের গ্যাস বিল আদায়', 'name_en' => 'Gas bill received from flat owners']
                    : ['code' => 'SERVICE_CHARGE', 'name_bn' => 'সার্ভিস চার্জ ও বিল আদায়', 'name_en' => 'Service charge received'];
                break;

            case Expense::class:
                $e = $ctx['expenses'][$r->ref_id] ?? null;
                $h = $e?->expenseHead;
                $head = ['code' => 'EXP_'.($h?->code ?? 'OTHER'), 'name_bn' => $h?->name_bn ?? 'খরচ', 'name_en' => $h?->name_en ?? 'Expense'];
                $g = $ctx['gas'][$r->ref_id] ?? null;
                if ($g) { // a gas purchase reads better with its quantity than with the voucher text
                    $particulars = sprintf('Gas purchase — %s kg @ %s%s%s', rtrim(rtrim(number_format((float) $g->quantity_kg, 2, '.', ''), '0'), '.'), rtrim(rtrim(number_format((float) $g->unit_price, 2, '.', ''), '0'), '.'), (float) $g->transport_cost > 0 ? ' + transport '.number_format((float) $g->transport_cost, 2) : '', $g->supplier_name ? ' ('.$g->supplier_name.')' : '');
                } elseif ($e?->payee || $e?->description) {
                    $particulars = trim(($e->description ?: $h?->name_en ?: 'Expense').($e->payee ? ' — '.$e->payee : ''));
                }
                break;

            case HallBookingPayment::class:
                $head = ['code' => 'HALL', 'name_bn' => 'কমিউনিটি হল', 'name_en' => 'Community hall'];
                break;

            case ParkingPayment::class:
                $head = ['code' => 'PARKING', 'name_bn' => 'পার্কিং', 'name_en' => 'Parking'];
                break;

            case Contribution::class:
                $b = ($ctx['contributions'][$r->ref_id] ?? null)?->head?->book;
                $head = ['code' => 'BOOK_'.($b?->code ?? 'X'), 'name_bn' => $b?->name_bn ?? 'আদায়', 'name_en' => $b?->name_en ?? 'Collection'];
                break;

            case TreasuryEntry::class:
                $t = $ctx['treasury'][$r->ref_id] ?? null;
                $funds = config('funds.funds');
                $other = fn (?string $code) => $funds[$code]['en'] ?? '';
                $reversal = $t?->reverses_id !== null;
                if ($t && in_array($t->kind, ['DEPOSIT_TO_BANK', 'WITHDRAW_FROM_BANK'], true)) {
                    $contra = true;
                } elseif ($t && $t->kind === 'OPENING') {
                    $opening = true;
                } elseif ($t && $t->kind === 'BANK_PROFIT' && ! $reversal) {
                    $head = ['code' => 'BANK_PROFIT', 'name_bn' => 'ব্যাংক মুনাফা', 'name_en' => 'Profit received from bank'];
                } elseif ($t && in_array($t->kind, ['FUND_LOAN', 'LOAN_REPAYMENT'], true) && ! $reversal) {
                    $counter = $dir === 'IN' ? ($t->kind === 'FUND_LOAN' ? $t->fund : $t->to_fund) : ($t->kind === 'FUND_LOAN' ? $t->to_fund : $t->fund);
                    $verb = [
                        'FUND_LOAN' => ['IN' => ['LOAN_TAKEN', 'ধার গ্রহণ', 'Loan taken from'], 'OUT' => ['LOAN_GIVEN', 'ধার প্রদান', 'Loan given to']],
                        'LOAN_REPAYMENT' => ['IN' => ['LOAN_REPAID_IN', 'ধার ফেরত পাওয়া', 'Loan repaid by'], 'OUT' => ['LOAN_REPAID_OUT', 'ধার শোধ', 'Loan repaid to']],
                    ][$t->kind][$dir];
                    $head = ['code' => $verb[0], 'name_bn' => $verb[1], 'name_en' => $verb[2]];
                    $particulars = $verb[2].' '.$other($counter).($t->notes ? ' — '.$t->notes : '');
                } elseif ($reversal) {
                    $head = ['code' => 'REVERSAL', 'name_bn' => 'বাতিল এন্ট্রি', 'name_en' => 'Reversal entries'];
                }
                break;
        }

        return [
            'id' => (int) $r->id,
            'date' => $r->txn_date->toDateString(),
            'medium' => $x['medium'],
            'dir' => $dir,
            'amount' => $x['amount'],
            'particulars' => $particulars,
            'head' => $head,
            'contra' => $contra,
            'opening' => $opening,
        ];
    }

    /** Net movement of one medium inside the month, contra rows included (they cancel across cash and bank). */
    private function sumFlow(Collection $items, string $from, string $to, string $medium): string
    {
        $net = '0';
        foreach ($items as $it) {
            if ($it['medium'] === $medium && $it['date'] >= $from && $it['date'] <= $to && ! $it['opening']) {
                $net = bcadd($net, $it['dir'] === 'IN' ? $it['amount'] : bcmul($it['amount'], '-1', 4), 4);
            }
        }

        return $net;
    }

    private function total(array $items): string
    {
        return bcadd(array_reduce($items, fn ($c, $i) => bcadd($c, $i['amount'], 4), '0'), '0', 4);
    }

    /** @return list<array{date:string, particulars:string, head:string, medium:string, amount:string}> */
    private function lines(array $items): array
    {
        return array_map(fn ($i) => ['date' => $i['date'], 'particulars' => $i['particulars'], 'head_code' => $i['head']['code'], 'head_name_bn' => $i['head']['name_bn'], 'head_name_en' => $i['head']['name_en'], 'medium' => $i['medium'], 'amount' => $i['amount']], $items);
    }

    private function byHead(array $items): array
    {
        $by = [];
        foreach ($items as $i) {
            $c = $i['head']['code'];
            $by[$c] ??= ['code' => $c, 'name_bn' => $i['head']['name_bn'], 'name_en' => $i['head']['name_en'], 'amount' => '0'];
            $by[$c]['amount'] = bcadd($by[$c]['amount'], $i['amount'], 4);
        }
        usort($by, fn ($a, $b) => bccomp($b['amount'], $a['amount'], 4));

        return $by;
    }

    /** Loans this fund has given or owes. $fund null = every pair. */
    private function loansFor(?string $fund): array
    {
        $funds = config('funds.funds');

        return array_values(array_map(fn ($p) => $p + [
            'lender_name_bn' => $funds[$p['lender']]['bn'], 'lender_name_en' => $funds[$p['lender']]['en'],
            'borrower_name_bn' => $funds[$p['borrower']]['bn'], 'borrower_name_en' => $funds[$p['borrower']]['en'],
        ], array_filter($this->treasury->loanPositions(), fn ($p) => $fund === null || $p['lender'] === $fund || $p['borrower'] === $fund)));
    }
}
