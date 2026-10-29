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
            $out['tenant_requests_pending'] = \App\Models\TenantRequest::where('status', 'PENDING')->count();
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

        return $out;
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
