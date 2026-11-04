<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\BillingPeriod;
use App\Models\Complaint;
use App\Models\Expense;
use App\Models\Flat;
use App\Models\HallBooking;
use App\Models\Meeting;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;

/**
 * The numbers on the home screen. Every block is only built for someone who is allowed to see that part of
 * the system (the same permissions that open the screens), so a caretaker never gets the accounts and a
 * cashier never gets the committee's meeting list. Nothing is stored; each call reads the live rows.
 */
class DashboardService
{
    public function __construct(
        private readonly GasBillingService $gas,
        private readonly ParkingService $parking,
        private readonly FundStatementService $funds,
        private readonly SupplierLedgerService $suppliers,
        private readonly ContributionService $contributions,
        private readonly WorkBudgetService $works,
    ) {}

    /** @return array<string, mixed> */
    public function overview(User $user, ?int $year = null, ?int $month = null): array
    {
        $today = now('Asia/Dhaka');
        $year ??= $today->year;
        $month ??= $today->month;
        $can = fn (string $p) => $user->hasRole('super_admin') || $user->can($p);

        $out = ['year' => $year, 'month' => $month, 'today' => $today->toDateString()];

        if ($can('billing.view')) {
            $out['billing'] = $this->billing($year, $month);
        }
        if ($can('accounting.view')) {
            $out['funds'] = $this->fundsBlock($year, $month);
        }
        if ($can('payment.view') || $can('expense.view')) {
            $out['approvals'] = [
                'payments' => $can('payment.view') ? Payment::where('status', 'PENDING')->count() : null,
                'expenses' => $can('expense.view') ? Expense::where('status', 'PENDING')->count() : null,
            ];
        }
        if ($can('tenant.manage') && \Illuminate\Support\Facades\Schema::hasTable('tenant_requests')) {
            $out['tenant_requests_pending'] = \App\Models\TenantRequest::where('status', 'PENDING')->count()
                // requests to end a tenancy wait for the same admin, so they are counted in the same chip
                + (\Illuminate\Support\Facades\Schema::hasTable('tenant_vacate_requests') ? \App\Models\TenantVacateRequest::where('status', 'PENDING')->count() : 0);
        }
        if ($can('payment.view')) {
            $out['recent_payments'] = Payment::with('flat')->latest('payment_date')->latest('id')->limit(5)->get()
                ->map(fn ($p) => [
                    'id' => $p->id, 'flat_id' => $p->flat_id, 'flat_no' => $p->flat?->flat_no, 'amount' => (string) $p->amount,
                    'date' => $p->payment_date->toDateString(), 'status' => $p->status,
                ])->all();
        }
        if ($can('gas.view')) {
            $out['gas'] = $this->gas->summary($year, $month);
        }
        if ($can('parking.view')) {
            $out['parking'] = $this->parking->summary($year, $month);
        }
        if ($can('hall.view')) {
            $out['hall'] = $this->hall($today);
        }
        if ($can('complaint.view')) {
            $out['complaints'] = [
                'open' => Complaint::whereIn('status', ['OPEN', 'IN_PROGRESS'])->count(),
                'urgent' => Complaint::whereIn('status', ['OPEN', 'IN_PROGRESS'])->where('priority', 'HIGH')->count(),
            ];
        }
        if ($can('meeting.view')) {
            $m = Meeting::where('status', 'SCHEDULED')->where('meeting_date', '>=', $today->toDateString())->orderBy('meeting_date')->first();
            $out['next_meeting'] = $m ? ['id' => $m->id, 'title' => $m->title, 'date' => $m->meeting_date->toDateString(), 'venue' => $m->venue] : null;
        }
        if ($can('contribution.view')) {
            $out['contributions'] = $this->contributionBooks();
        }
        if ($can('expense.view')) {
            $owed = $this->suppliers->suppliers('materials')['totals']['owed'];
            $out['supplier_owed'] = ['materials' => $owed, 'gas' => $this->suppliers->suppliers('gas')['totals']['owed']];
        }
        if ($can('flat.view')) {
            $out['flats'] = ['total' => Flat::count()];
        }
        if ($can('expense.view') && \Illuminate\Support\Facades\Schema::hasTable('water_bills')) {
            // WASA bills arrive during the month: after the 10th, a month with no bill posted yet is worth a reminder
            $out['water_bill_missing'] = $today->day >= 10
                && ! \App\Models\WaterBill::where('bill_year', $today->year)->where('bill_month', $today->month)->exists();
        }

        $head = $this->headline($out, $can);
        if ($head !== []) {
            $out['headline'] = $head;
        }

        return $out;
    }

    /**
     * The three numbers the committee looks at first: how the service-charge money stands (what is in hand and what the
     * flats still owe), how the project (capital) fund stands, and gas - the fund, what flats owe for gas and what is
     * owed to the gas supplier. Each shows a surplus or a deficit.
     *
     * @param  array<string, mixed>  $out
     * @return array<string, mixed>
     */
    private function headline(array $out, callable $can): array
    {
        $fund = fn (string $code) => collect($out['funds']['funds'] ?? [])->firstWhere('code', $code);
        $head = [];

        if (($op = $fund('OPERATING')) !== null) {
            $gasDue = $this->gasReceivable();
            $outstanding = (string) ($out['billing']['outstanding_all'] ?? 0);
            $head['service'] = [
                'balance' => $op['closing'], 'cash' => $op['cash'], 'bank' => $op['bank'],
                'receivable' => isset($out['billing']) ? bcsub($outstanding, min($gasDue, $outstanding), 4) : null,
                'month_billed' => $out['billing']['billed'] ?? null, 'month_collected' => $out['billing']['collected'] ?? null,
                'month_receipts' => $op['receipts'], 'month_payments' => $op['payments'],
            ];
        }

        if (($cap = $fund('CAPITAL')) !== null) {
            $toCollect = '0';
            $open = 0;
            if ($can('budget.view') && \Illuminate\Support\Facades\Schema::hasTable('work_budgets')) {
                foreach (\App\Models\WorkBudget::with('book')->whereIn('status', ['PLANNED', 'ONGOING'])->get() as $w) {
                    $toCollect = bcadd($toCollect, $this->works->summary($w)['still_to_collect'], 4);
                    $open++;
                }
            }
            $head['project'] = [
                'balance' => $cap['closing'], 'cash' => $cap['cash'], 'bank' => $cap['bank'],
                'works_open' => $open, 'works_to_collect' => $toCollect,
                'month_receipts' => $cap['receipts'], 'month_payments' => $cap['payments'],
            ];
        }

        if (($gas = $fund('GAS')) !== null) {
            $receivable = $this->gasReceivable();
            $payable = $can('gas.view') || $can('expense.view') ? (string) $this->suppliers->suppliers('gas')['totals']['owed'] : '0';
            $head['gas'] = [
                'balance' => $gas['closing'], 'receivable' => $receivable, 'payable' => bcadd($payable, '0', 4),
                // what the gas side would stand at if every flat paid its gas bill and the supplier were paid off
                'net' => bcsub(bcadd((string) $gas['closing'], $receivable, 4), $payable, 4),
            ];
        }

        return $head;
    }

    /** The gas part of what flats still owe: each unpaid bill's balance, in the proportion gas makes up of that bill. */
    private function gasReceivable(): string
    {
        $v = Bill::selectRaw('COALESCE(SUM(CASE WHEN total_payable > paid_amount AND total_payable > 0 THEN (total_payable - paid_amount) * gas_amount / total_payable ELSE 0 END), 0) AS t')->value('t');

        return bcadd((string) ($v ?? 0), '0', 4);
    }

    /** This month's bills, plus the last six months for the trend bars. */
    private function billing(int $year, int $month): array
    {
        $sum = fn (?BillingPeriod $p) => $p
            ? Bill::where('billing_period_id', $p->id)->selectRaw('COALESCE(SUM(total_payable),0) billed, COALESCE(SUM(paid_amount),0) paid')->first()
            : null;

        $period = BillingPeriod::where('year', $year)->where('month', $month)->first();
        $now = $sum($period);
        $billed = bcadd((string) ($now->billed ?? 0), '0', 4);
        $paid = bcadd((string) ($now->paid ?? 0), '0', 4);

        $counts = $period
            ? Bill::where('billing_period_id', $period->id)->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status')
            : collect();

        $trend = [];
        $cursor = Carbon::create($year, $month, 1)->subMonths(5);
        for ($i = 0; $i < 6; $i++) {
            $p = BillingPeriod::where('year', $cursor->year)->where('month', $cursor->month)->first();
            $s = $sum($p);
            $trend[] = [
                'year' => $cursor->year, 'month' => $cursor->month,
                'billed' => bcadd((string) ($s->billed ?? 0), '0', 4), 'paid' => bcadd((string) ($s->paid ?? 0), '0', 4),
            ];
            $cursor->addMonth();
        }

        return [
            'has_bills' => (bool) $period && ($counts->sum() > 0),
            'billed' => $billed,
            'collected' => $paid,
            'due' => bcsub($billed, $paid, 4),
            'percent' => bccomp($billed, '0', 4) > 0 ? round((float) $paid / (float) $billed * 100, 1) : null,
            'paid_flats' => (int) ($counts['PAID'] ?? 0),
            'partial_flats' => (int) ($counts['PARTIAL'] ?? 0),
            'unpaid_flats' => (int) ($counts['UNPAID'] ?? 0),
            // everything still owed across all months, not only this one
            'outstanding_all' => bcadd((string) (Bill::where('status', '!=', 'PAID')->selectRaw('COALESCE(SUM(total_payable - paid_amount),0) t')->value('t') ?? 0), '0', 4),
            'trend' => $trend,
        ];
    }

    private function fundsBlock(int $year, int $month): array
    {
        $s = $this->funds->summary($year, $month);

        return [
            'total_closing' => $s['total']['closing'],
            'funds' => array_map(fn ($f) => [
                'code' => $f['fund']['code'] ?? ($f['fund'] ?? null),
                'name_bn' => $f['fund']['name_bn'] ?? null,
                'name_en' => $f['fund']['name_en'] ?? null,
                'closing' => $f['closing'], 'cash' => $f['closing_cash'], 'bank' => $f['closing_bank'],
                'receipts' => $f['receipts'], 'payments' => $f['payments'],
            ], $s['funds']),
        ];
    }

    private function hall(Carbon $today): array
    {
        $upcoming = HallBooking::whereIn('status', ['PENDING', 'CONFIRMED'])->where('booking_date', '>=', $today->toDateString())
            ->orderBy('booking_date')->limit(4)->get();

        return [
            'pending_requests' => HallBooking::where('status', 'PENDING')->count(),
            'upcoming' => $upcoming->map(fn ($b) => [
                'id' => $b->id, 'date' => $b->booking_date->toDateString(), 'slot' => $b->slot, 'event' => $b->event_type,
                'customer' => $b->customer_name, 'status' => $b->status,
            ])->all(),
        ];
    }

    private function contributionBooks(): array
    {
        $rows = [];
        foreach ($this->contributions->books() as $book) {
            $st = $this->contributions->statement($book);
            $target = $st['totals']['target'] ?? '0';
            $collected = $st['totals']['collected'] ?? '0';
            $rows[] = [
                'code' => $book->code, 'name_bn' => $book->name_bn, 'name_en' => $book->name_en,
                'collected' => $collected, 'target' => $target, 'has_target' => (bool) $book->has_target,
                'percent' => bccomp((string) $target, '0', 4) > 0 ? round((float) $collected / (float) $target * 100, 1) : null,
            ];
        }

        return $rows;
    }
}
