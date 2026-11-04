<?php

namespace App\Services;

use App\Models\BankTransaction;
use App\Models\Bill;
use App\Models\CashLedger;
use App\Models\Expense;
use App\Models\Flat;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * The everyday reports a Bangladeshi building committee actually asks for, in plain words: who owes how much, what was collected,
 * what was spent, one flat's statement, and the cash book / bank book. Every report has the same shape - columns, rows, a summary
 * and totals - so one screen, one PDF layout and one Excel layout serve them all. All figures are worked out here.
 *
 * Column type: 'text' | 'money' | 'date'.
 */
class SimpleReportService
{
    public const TYPES = ['dues', 'purposes', 'collections', 'expenses', 'flat', 'cashbook'];

    private const METHOD = ['CASH' => 'নগদ', 'BANK' => 'ব্যাংক/চেক', 'BKASH' => 'bKash', 'NAGAD' => 'Nagad', 'ROCKET' => 'Rocket', 'UPAY' => 'Upay'];

    private const FUND = ['OPERATING' => 'সার্ভিস চার্জ', 'GAS' => 'গ্যাস', 'CAPITAL' => 'প্রজেক্ট ফান্ড', 'SINKING' => 'সঞ্চিত তহবিল'];

    private const MONTHS = ['', 'জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];

    public function __construct(private readonly BillBalanceService $balances, private readonly WorkBudgetService $works) {}

    /** @param  array<string, mixed>  $p  from, to, flat_id, medium */
    public function run(string $type, array $p): array
    {
        return match ($type) {
            'dues' => $this->dues(),
            'purposes' => $this->purposes(),
            'collections' => $this->collections($this->from($p), $this->to($p)),
            'expenses' => $this->expenses($this->from($p), $this->to($p)),
            'flat' => $this->flat((int) ($p['flat_id'] ?? 0), $this->from($p), $this->to($p)),
            'cashbook' => $this->cashbook((string) ($p['medium'] ?? 'CASH'), $this->from($p), $this->to($p)),
            default => throw ValidationException::withMessages(['type' => 'অজানা রিপোর্ট।']),
        };
    }

    private function from(array $p): string
    {
        return $p['from'] ?? now('Asia/Dhaka')->startOfMonth()->toDateString();
    }

    private function to(array $p): string
    {
        return $p['to'] ?? now('Asia/Dhaka')->toDateString();
    }

    private function period(string $from, string $to): string
    {
        return Carbon::parse($from)->format('d/m/Y').' — '.Carbon::parse($to)->format('d/m/Y');
    }

    private function sum(array $rows, string $key): string
    {
        return bcadd(array_reduce($rows, fn ($c, $r) => bcadd($c, (string) $r[$key], 4), '0'), '0', 2);
    }

    // ---- who owes how much

    public function dues(): array
    {
        $rows = [];
        foreach ($this->balances->latestPerFlat() as $b) {
            $due = $this->balances->due($b);
            if (bccomp($due, '0', 4) <= 0) {
                continue;
            }
            $names = $b->flat->occupantNames();
            $rows[] = [
                'flat' => $b->flat->flat_no, 'owner' => $names['owner'] ?? '—', 'tenant' => $names['tenant'] ?? '—',
                'mobile' => $b->flat->currentOwners->first()?->person?->mobile ?? '—',
                'last_bill' => self::MONTHS[$b->billingPeriod->month].' '.$b->billingPeriod->year,
                'billed' => bcadd((string) $b->total_payable, '0', 2), 'paid' => bcadd((string) $b->paid_amount, '0', 2),
                'gas' => bcadd($this->balances->gasPart($b), '0', 2), 'due' => bcadd($due, '0', 2),
            ];
        }
        usort($rows, fn ($a, $c) => bccomp($c['due'], $a['due'], 2));

        return [
            'type' => 'dues', 'title' => 'বকেয়া তালিকা', 'subtitle' => 'আজ পর্যন্ত কোন ফ্ল্যাটের কাছে কত টাকা পাওনা (সর্বশেষ বিল অনুযায়ী)',
            'period' => now('Asia/Dhaka')->format('d/m/Y'),
            'columns' => [
                ['key' => 'flat', 'label' => 'ফ্ল্যাট', 'type' => 'text'], ['key' => 'owner', 'label' => 'মালিক', 'type' => 'text'], ['key' => 'tenant', 'label' => 'ভাড়াটিয়া', 'type' => 'text'],
                ['key' => 'mobile', 'label' => 'মোবাইল', 'type' => 'text'], ['key' => 'last_bill', 'label' => 'সর্বশেষ বিল', 'type' => 'text'],
                ['key' => 'billed', 'label' => 'মোট বিল', 'type' => 'money'], ['key' => 'paid', 'label' => 'পরিশোধ', 'type' => 'money'],
                ['key' => 'gas', 'label' => 'তার মধ্যে গ্যাস', 'type' => 'money'], ['key' => 'due', 'label' => 'বাকি', 'type' => 'money'],
            ],
            'rows' => $rows,
            'summary' => [['label' => 'বকেয়া আছে এমন ফ্ল্যাট', 'value' => (string) count($rows)], ['label' => 'মোট বকেয়া', 'value' => $this->sum($rows, 'due'), 'type' => 'money'], ['label' => 'তার মধ্যে গ্যাস বিল', 'value' => $this->sum($rows, 'gas'), 'type' => 'money']],
            'totals' => ['billed' => $this->sum($rows, 'billed'), 'paid' => $this->sum($rows, 'paid'), 'gas' => $this->sum($rows, 'gas'), 'due' => $this->sum($rows, 'due')],
        ];
    }

    // ---- each flat's dues, purpose by purpose

    /**
     * Every kind of payment is its own account: the monthly subscription (service charge and gas bill) and each special collection
     * (gas line, generator, east wall, old water dues ...). One column per purpose, so "who has not paid for what" reads off one sheet.
     */
    public function purposes(): array
    {
        // the special collections arrive with the database upgrade; until then there is only the monthly subscription
        $works = \Illuminate\Support\Facades\Schema::hasTable('work_budgets')
            ? \App\Models\WorkBudget::where('status', '!=', 'CANCELLED')->orderBy('id')->get()
            : collect();
        $latest = $this->balances->latestPerFlat()->keyBy('flat_id');
        $columns = [
            ['key' => 'flat', 'label' => 'ফ্ল্যাট', 'type' => 'text'], ['key' => 'owner', 'label' => 'মালিক', 'type' => 'text'], ['key' => 'mobile', 'label' => 'মোবাইল', 'type' => 'text'],
            ['key' => 'bill', 'label' => 'মাসিক চাঁদা (সার্ভিস চার্জ ও গ্যাস বিল)', 'type' => 'money'],
        ];
        foreach ($works as $w) {
            $columns[] = ['key' => 'w'.$w->id, 'label' => $w->title, 'type' => 'money'];
        }
        $columns[] = ['key' => 'total', 'label' => 'মোট বাকি', 'type' => 'money'];

        $rows = [];
        $totals = array_fill_keys(array_column($columns, 'key'), '0');
        foreach (Flat::with('currentOwners.person', 'currentTenant.person')->get()->sortBy('flat_no', SORT_NATURAL) as $flat) {
            $bill = isset($latest[$flat->id]) ? $this->balances->due($latest[$flat->id]) : '0';
            $row = ['flat' => $flat->flat_no, 'owner' => $flat->occupantNames()['owner'] ?? '—', 'mobile' => $flat->currentOwners->first()?->person?->mobile ?? '—', 'bill' => bcadd($bill, '0', 2)];
            $total = $bill;
            $byWork = collect($this->works->forFlat($flat, all: true))->keyBy('work_id');
            foreach ($works as $w) {
                $due = isset($byWork[$w->id]) ? $byWork[$w->id]['due'] : '0';
                $row['w'.$w->id] = bcadd($due, '0', 2);
                $total = bcadd($total, $due, 4);
            }
            $row['total'] = bcadd($total, '0', 2);
            if (bccomp($row['total'], '0', 2) <= 0) {
                continue;
            }
            foreach (array_keys($totals) as $k) {
                if ($k !== 'flat' && $k !== 'owner' && $k !== 'mobile') {
                    $totals[$k] = bcadd($totals[$k], $row[$k], 2);
                }
            }
            $rows[] = $row;
        }
        usort($rows, fn ($a, $b) => bccomp($b['total'], $a['total'], 2));
        unset($totals['flat'], $totals['owner'], $totals['mobile']);

        $summary = [['label' => 'বকেয়া আছে এমন ফ্ল্যাট', 'value' => (string) count($rows)], ['label' => 'মোট বকেয়া', 'value' => $totals['total'], 'type' => 'money']];
        foreach ($works as $w) {
            $summary[] = ['label' => $w->title, 'value' => $totals['w'.$w->id], 'type' => 'money'];
        }

        return [
            'type' => 'purposes', 'title' => 'খাত অনুযায়ী বকেয়া', 'subtitle' => 'প্রতিটি খাতের (মাসিক চাঁদা, গ্যাস লাইন, জেনারেটর, পুরনো পানির বকেয়া, পূর্ব দেয়াল ...) আলাদা বকেয়া',
            'period' => now('Asia/Dhaka')->format('d/m/Y'), 'columns' => $columns, 'rows' => $rows, 'summary' => $summary, 'totals' => $totals,
        ];
    }

    // ---- what came in

    public function collections(string $from, string $to): array
    {
        $payments = Payment::with('flat.currentOwners.person')->where('status', 'APPROVED')->whereBetween('payment_date', [$from, $to])
            ->orderBy('payment_date')->orderBy('id')->get();
        $rows = $payments->map(fn (Payment $p) => [
            'date' => $p->payment_date->toDateString(), 'receipt' => $p->receipt_no, 'flat' => $p->flat?->flat_no, 'owner' => $p->flat?->occupantNames()['owner'] ?? '—',
            'method' => self::METHOD[$p->method] ?? $p->method, 'reference' => trim(($p->bank_name ? $p->bank_name.' ' : '').($p->reference_no ?? '')) ?: '—', 'amount' => bcadd((string) $p->amount, '0', 2),
        ])->all();

        $byMethod = [];
        foreach ($payments as $p) {
            $k = self::METHOD[$p->method] ?? $p->method;
            $byMethod[$k] = bcadd($byMethod[$k] ?? '0', (string) $p->amount, 2);
        }
        $summary = [['label' => 'মোট আদায়', 'value' => $this->sum($rows, 'amount'), 'type' => 'money'], ['label' => 'রসিদের সংখ্যা', 'value' => (string) count($rows)]];
        foreach ($byMethod as $k => $v) {
            $summary[] = ['label' => $k, 'value' => $v, 'type' => 'money'];
        }

        return [
            'type' => 'collections', 'title' => 'আদায় রিপোর্ট', 'subtitle' => 'ফ্ল্যাট থেকে সার্ভিস চার্জ ও গ্যাস বিলের আদায় (অনুমোদিত পেমেন্ট)', 'period' => $this->period($from, $to),
            'columns' => [
                ['key' => 'date', 'label' => 'তারিখ', 'type' => 'date'], ['key' => 'receipt', 'label' => 'রসিদ নং', 'type' => 'text'], ['key' => 'flat', 'label' => 'ফ্ল্যাট', 'type' => 'text'],
                ['key' => 'owner', 'label' => 'মালিক', 'type' => 'text'], ['key' => 'method', 'label' => 'মাধ্যম', 'type' => 'text'], ['key' => 'reference', 'label' => 'ব্যাংক / রেফারেন্স', 'type' => 'text'],
                ['key' => 'amount', 'label' => 'টাকা', 'type' => 'money'],
            ],
            'rows' => $rows, 'summary' => $summary, 'totals' => ['amount' => $this->sum($rows, 'amount')],
        ];
    }

    // ---- what went out

    public function expenses(string $from, string $to): array
    {
        $list = Expense::with('expenseHead')->where('status', 'APPROVED')->whereBetween('date', [$from, $to])->orderBy('date')->orderBy('id')->get();
        $rows = $list->map(fn (Expense $e) => [
            'date' => $e->date->toDateString(), 'voucher' => $e->voucher_no, 'head' => $e->expenseHead?->name_bn ?? '—', 'payee' => $e->payee,
            'description' => $e->description ?: '—', 'medium' => $e->treasury_medium === 'CASH' ? 'নগদ' : 'ব্যাংক', 'amount' => bcadd((string) $e->amount, '0', 2),
        ])->all();

        $byHead = [];
        foreach ($rows as $r) {
            $byHead[$r['head']] = bcadd($byHead[$r['head']] ?? '0', $r['amount'], 2);
        }
        arsort($byHead);
        $summary = [['label' => 'মোট খরচ', 'value' => $this->sum($rows, 'amount'), 'type' => 'money'], ['label' => 'ভাউচারের সংখ্যা', 'value' => (string) count($rows)]];
        foreach ($byHead as $k => $v) {
            $summary[] = ['label' => $k, 'value' => $v, 'type' => 'money'];
        }

        return [
            'type' => 'expenses', 'title' => 'খরচের রিপোর্ট', 'subtitle' => 'অনুমোদিত খরচ — খাত অনুযায়ী যোগফলসহ', 'period' => $this->period($from, $to),
            'columns' => [
                ['key' => 'date', 'label' => 'তারিখ', 'type' => 'date'], ['key' => 'voucher', 'label' => 'ভাউচার', 'type' => 'text'], ['key' => 'head', 'label' => 'খাত', 'type' => 'text'],
                ['key' => 'payee', 'label' => 'কাকে', 'type' => 'text'], ['key' => 'description', 'label' => 'বিবরণ', 'type' => 'text'], ['key' => 'medium', 'label' => 'মাধ্যম', 'type' => 'text'],
                ['key' => 'amount', 'label' => 'টাকা', 'type' => 'money'],
            ],
            'rows' => $rows, 'summary' => $summary, 'totals' => ['amount' => $this->sum($rows, 'amount')],
        ];
    }

    // ---- one flat

    public function flat(int $flatId, string $from, string $to): array
    {
        $flat = Flat::with('currentOwners.person', 'currentTenant.person')->find($flatId) ?? throw ValidationException::withMessages(['flat_id' => 'ফ্ল্যাট বাছুন।']);
        $fromKey = (int) Carbon::parse($from)->format('Ym');
        $toKey = (int) Carbon::parse($to)->format('Ym');

        $bills = Bill::with('billingPeriod')->where('flat_id', $flat->id)->get()
            ->filter(fn (Bill $b) => ($k = $b->billingPeriod->year * 100 + $b->billingPeriod->month) >= $fromKey && $k <= $toKey)
            ->sortBy(fn (Bill $b) => $b->billingPeriod->year * 100 + $b->billingPeriod->month)->values();
        $rows = $bills->map(fn (Bill $b) => [
            'month' => self::MONTHS[$b->billingPeriod->month].' '.$b->billingPeriod->year, 'previous' => bcadd((string) $b->previous_due, '0', 2),
            'service' => bcadd((string) $b->service_charge, '0', 2), 'gas' => bcadd((string) $b->gas_amount, '0', 2),
            'other' => bcadd(bcadd((string) $b->other_charges, (string) $b->late_fee, 4), '0', 2), 'total' => bcadd((string) $b->total_payable, '0', 2),
            'paid' => bcadd((string) $b->paid_amount, '0', 2), 'due' => bcadd($this->balances->due($b), '0', 2),
        ])->all();

        $latest = $this->balances->latestPerFlat()->firstWhere('flat_id', $flat->id);
        $names = $flat->occupantNames();

        return [
            'type' => 'flat', 'title' => 'ফ্ল্যাট '.$flat->flat_no.' — হিসাব বিবরণী', 'subtitle' => 'মালিক: '.($names['owner'] ?? '—').($names['tenant'] ? ' · ভাড়াটিয়া: '.$names['tenant'] : ''),
            'period' => $this->period($from, $to),
            'columns' => [
                ['key' => 'month', 'label' => 'মাস', 'type' => 'text'], ['key' => 'previous', 'label' => 'আগের বাকি', 'type' => 'money'], ['key' => 'service', 'label' => 'সার্ভিস চার্জ', 'type' => 'money'],
                ['key' => 'gas', 'label' => 'গ্যাস', 'type' => 'money'], ['key' => 'other', 'label' => 'অন্যান্য', 'type' => 'money'], ['key' => 'total', 'label' => 'মোট বিল', 'type' => 'money'],
                ['key' => 'paid', 'label' => 'পরিশোধ', 'type' => 'money'], ['key' => 'due', 'label' => 'বাকি', 'type' => 'money'],
            ],
            'rows' => $rows,
            'summary' => [['label' => 'এখন মোট বাকি (সর্বশেষ বিল অনুযায়ী)', 'value' => $latest ? bcadd($this->balances->due($latest), '0', 2) : '0.00', 'type' => 'money'], ['label' => 'এই সময়ে পরিশোধ', 'value' => $this->sum($rows, 'paid'), 'type' => 'money']],
            'totals' => [],
        ];
    }

    // ---- cash book / bank book

    public function cashbook(string $medium, string $from, string $to): array
    {
        if (! in_array($medium, ['CASH', 'BANK'], true)) {
            throw ValidationException::withMessages(['medium' => 'ক্যাশ বা ব্যাংক বাছুন।']);
        }
        $cash = $medium === 'CASH';
        $opening = $cash
            ? bcsub((string) (CashLedger::where('txn_date', '<', $from)->sum('in_amount') ?: 0), (string) (CashLedger::where('txn_date', '<', $from)->sum('out_amount') ?: 0), 4)
            : bcsub((string) (BankTransaction::where('txn_date', '<', $from)->sum('debit') ?: 0), (string) (BankTransaction::where('txn_date', '<', $from)->sum('credit') ?: 0), 4);

        $lines = $cash
            ? CashLedger::whereBetween('txn_date', [$from, $to])->orderBy('txn_date')->orderBy('id')->get()->map(fn ($l) => ['date' => $l->txn_date->toDateString(), 'what' => $l->particulars, 'fund' => $l->fund_type, 'in' => (string) $l->in_amount, 'out' => (string) $l->out_amount])
            : BankTransaction::whereBetween('txn_date', [$from, $to])->orderBy('txn_date')->orderBy('id')->get()->map(fn ($l) => ['date' => $l->txn_date->toDateString(), 'what' => $l->particulars, 'fund' => $l->fund_type, 'in' => (string) $l->debit, 'out' => (string) $l->credit]);

        $run = $opening;
        $rows = [['date' => $from, 'what' => 'শুরুর জের (আগের স্থিতি)', 'fund' => '', 'in' => '', 'out' => '', 'balance' => bcadd($opening, '0', 2)]];
        $in = '0';
        $out = '0';
        foreach ($lines as $l) {
            $run = bcadd(bcsub($run, $l['out'], 4), $l['in'], 4);
            $in = bcadd($in, $l['in'], 4);
            $out = bcadd($out, $l['out'], 4);
            $rows[] = ['date' => $l['date'], 'what' => $l['what'], 'fund' => self::FUND[$l['fund']] ?? $l['fund'], 'in' => bccomp($l['in'], '0', 4) > 0 ? bcadd($l['in'], '0', 2) : '', 'out' => bccomp($l['out'], '0', 4) > 0 ? bcadd($l['out'], '0', 2) : '', 'balance' => bcadd($run, '0', 2)];
        }

        return [
            'type' => 'cashbook', 'title' => $cash ? 'ক্যাশ বই' : 'ব্যাংক বই', 'subtitle' => $cash ? 'নগদ টাকার জমা-খরচ ও স্থিতি' : 'ব্যাংকে জমা-উত্তোলন ও স্থিতি', 'period' => $this->period($from, $to),
            'columns' => [
                ['key' => 'date', 'label' => 'তারিখ', 'type' => 'date'], ['key' => 'what', 'label' => 'বিবরণ', 'type' => 'text'], ['key' => 'fund', 'label' => 'তহবিল', 'type' => 'text'],
                ['key' => 'in', 'label' => 'জমা', 'type' => 'money'], ['key' => 'out', 'label' => 'খরচ/উত্তোলন', 'type' => 'money'], ['key' => 'balance', 'label' => 'স্থিতি', 'type' => 'money'],
            ],
            'rows' => $rows,
            'summary' => [['label' => 'শুরুর স্থিতি', 'value' => bcadd($opening, '0', 2), 'type' => 'money'], ['label' => 'মোট জমা', 'value' => bcadd($in, '0', 2), 'type' => 'money'], ['label' => 'মোট খরচ', 'value' => bcadd($out, '0', 2), 'type' => 'money'], ['label' => 'শেষ স্থিতি', 'value' => bcadd($run, '0', 2), 'type' => 'money']],
            'totals' => ['in' => bcadd($in, '0', 2), 'out' => bcadd($out, '0', 2)],
        ];
    }
}
