<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Complaint;
use App\Models\Flat;
use App\Models\FlatParty;
use App\Models\Notice;
use App\Models\ParkingAllocation;
use App\Models\Payment;
use App\Models\Resolution;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * What a flat owner or tenant sees on their home screen and in their bills. Everything is scoped to the flats the person
 * currently holds (owner or tenant), resolved through flat_parties; a flat that is not theirs is never reachable.
 */
class ResidentPortalService
{
    public function __construct(
        private readonly BillPrintService $slips,
        private readonly ContributionService $contributions,
        private readonly TenantKycService $kyc,
    ) {}

    /** @return \Illuminate\Support\Collection<int, FlatParty> */
    private function parties(User $user)
    {
        return $user->person_id ? FlatParty::where('person_id', $user->person_id)->whereNull('to_date')->get() : collect();
    }

    /** @return list<int> */
    public function flatIds(User $user): array
    {
        return $this->parties($user)->pluck('flat_id')->unique()->values()->all();
    }

    /** A bill belongs to the person when it is for a flat they hold. */
    public function ownsBill(User $user, Bill $bill): bool
    {
        return in_array((int) $bill->flat_id, $this->flatIds($user), true);
    }

    /** @return array<string, mixed> */
    public function summary(User $user): array
    {
        $parties = $this->parties($user);
        $flats = Flat::whereIn('id', $parties->pluck('flat_id'))->with('currentOwners.person', 'currentTenant.person')->get()
            ->sortBy(fn (Flat $f) => [(int) $f->flat_no, (string) $f->flat_no])->values();
        $today = now('Asia/Dhaka')->toDateString();

        $due = '0';
        $overdue = false;
        $out = [];
        foreach ($flats as $flat) {
            $role = $parties->where('flat_id', $flat->id)->contains('role', 'OWNER') ? 'OWNER' : 'TENANT';

            $bill = Bill::query()->join('billing_periods as bp', 'bp.id', '=', 'bills.billing_period_id')
                ->where('bills.flat_id', $flat->id)->orderByRaw('(bp.year * 100 + bp.month) desc')->select('bills.*')->first();
            $latest = null;
            if ($bill) {
                $slip = $this->slips->slipFor($bill);
                $isOverdue = bccomp($slip['due'], '0', 4) > 0 && $slip['due_date_iso'] && $slip['due_date_iso'] < $today;
                $due = bcadd($due, bccomp($slip['due'], '0', 4) > 0 ? $slip['due'] : '0', 4);
                $overdue = $overdue || $isOverdue;
                $latest = [
                    'id' => $bill->id, 'month_label' => $slip['month_label'], 'total_payable' => $slip['total_payable'], 'paid_amount' => $slip['paid_amount'],
                    'due' => $slip['due'], 'due_date' => $slip['due_date'], 'status' => $slip['status'], 'overdue' => $isOverdue,
                ];
            }

            // the owner sees who lives in the flat and whether that person's KYC is done; a tenant does not need this
            $tenant = null;
            if ($role === 'OWNER' && ($t = $flat->currentTenant->first())) {
                $present = $this->kyc->present($t);
                $tenant = ['name' => $t->person?->name_en ?: $t->person?->name_bn, 'mobile' => $t->person?->mobile, 'since' => $t->start_date?->toDateString(), 'kyc_complete' => $present['complete']];
            }

            $out[] = [
                'id' => $flat->id, 'flat_no' => $flat->flat_no, 'floor' => $flat->floor, 'size_sqft' => $flat->size_sqft, 'gas_meter_no' => $flat->gas_meter_no,
                'occupancy_status' => $flat->occupancy_status, 'role' => $role, 'tenant' => $tenant, 'latest_bill' => $latest,
                'parking' => ParkingAllocation::with('slot')->where('flat_id', $flat->id)->whereNull('to_date')->get()
                    ->map(fn ($a) => ['slot' => $a->slot?->code, 'vehicle_no' => $a->vehicle_no])->values()->all(),
            ];
        }

        // what the owner still owes on the construction books (a tenant has no share)
        $constructionDue = null;
        $ownedFlats = $flats->filter(fn ($f) => $parties->where('flat_id', $f->id)->contains('role', 'OWNER'));
        if ($ownedFlats->isNotEmpty()) {
            $constructionDue = '0';
            foreach ($ownedFlats as $f) {
                foreach ($this->contributions->flatLedgers($f) as $ledger) {
                    if ($ledger['balance'] !== null && bccomp((string) $ledger['balance'], '0', 4) > 0) {
                        $constructionDue = bcadd($constructionDue, (string) $ledger['balance'], 4);
                    }
                }
            }
        }

        $flatIds = $flats->pluck('id');

        return [
            'flats' => $out,
            'totals' => [
                'bill_due' => $due,
                'overdue' => $overdue,
                'pending_payments' => (string) (Payment::whereIn('flat_id', $flatIds)->where('status', 'PENDING')->sum('amount') ?: 0),
                'construction_due' => $constructionDue,
            ],
            'counts' => [
                'unread_notifications' => Schema::hasTable('notifications') ? $user->unreadNotifications()->count() : 0,
                'new_notices' => Notice::whereNotNull('published_at')->where('published_at', '>=', now()->subDays(30))->count(),
                'open_complaints' => Complaint::whereIn('flat_id', $flatIds)->whereIn('status', ['OPEN', 'IN_PROGRESS'])->count(),
            ],
        ];
    }

    /**
     * The flat's bills with the full breakdown and the gas meter figures, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function bills(User $user): array
    {
        $bills = Bill::whereIn('flat_id', $this->flatIds($user))->with('billingPeriod')->get()
            ->sortByDesc(fn (Bill $b) => $b->billingPeriod->year * 100 + $b->billingPeriod->month)->values();

        return $bills->map(fn (Bill $b) => $this->slips->slipFor($b))->all();
    }

    /**
     * What the committee has decided: the resolutions of meetings that were held (not the ones called off).
     *
     * @return list<array<string, mixed>>
     */
    public function decisions(): array
    {
        return Resolution::with('meeting')->where('status', '!=', 'CANCELLED')
            ->whereHas('meeting', fn ($q) => $q->where('status', 'HELD'))
            ->get()->sortByDesc(fn ($r) => $r->meeting->meeting_date)->take(40)->map(fn (Resolution $r) => [
                'id' => $r->id, 'resolution_no' => $r->resolution_no, 'meeting_title' => $r->meeting->title, 'meeting_date' => $r->meeting->meeting_date?->toDateString(),
                'description' => $r->description, 'decision' => $r->decision, 'status' => $r->status,
            ])->values()->all();
    }
}
