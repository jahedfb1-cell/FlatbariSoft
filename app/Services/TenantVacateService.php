<?php

namespace App\Services;

use App\Models\FlatParty;
use App\Models\Tenant;
use App\Models\TenantVacateRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "My tenant is leaving." The owner (for their own flat) or the manager (any flat) sends the request; only an
 * admin accepting it actually ends the tenancy - through the same FlatOccupancyService the admin's own
 * "vacate" button uses, so the flat goes back to EMPTY and the tenant's party record is closed.
 */
class TenantVacateService
{
    public function __construct(private readonly FlatOccupancyService $occupancy) {}

    /** The flats this person may speak for: their own as an owner, or every flat for the manager / admin. */
    public function canSpeakFor(User $user, int $flatId): bool
    {
        if ($user->hasRole('super_admin') || $user->can('tenant.request')) {
            return true;
        }

        return $user->person_id
            && FlatParty::where('person_id', $user->person_id)->where('flat_id', $flatId)->where('role', 'OWNER')->whereNull('to_date')->exists();
    }

    /** @return list<array<string, mixed>> the current tenants this person may send a leave request for */
    public function tenantsFor(User $user): array
    {
        $q = Tenant::with('person', 'flat')->where('status', 'ACTIVE');
        if (! ($user->hasRole('super_admin') || $user->can('tenant.request'))) {
            $own = $user->person_id
                ? FlatParty::where('person_id', $user->person_id)->where('role', 'OWNER')->whereNull('to_date')->pluck('flat_id')
                : collect();
            $q->whereIn('flat_id', $own);
        }
        $waiting = TenantVacateRequest::where('status', 'PENDING')->pluck('tenant_id')->all();

        return $q->orderBy('flat_id')->get()->map(fn ($t) => [
            'tenant_id' => $t->id, 'flat_id' => $t->flat_id, 'flat_no' => $t->flat?->flat_no,
            'name' => $t->person?->name_bn, 'mobile' => $t->person?->mobile, 'start_date' => $t->start_date?->toDateString(),
            'has_pending' => in_array($t->id, $waiting, true),
        ])->all();
    }

    public function submit(User $by, int $tenantId, string $endDate, ?string $note): TenantVacateRequest
    {
        $tenant = Tenant::findOrFail($tenantId);

        if ($tenant->status !== 'ACTIVE') {
            throw ValidationException::withMessages(['tenant_id' => 'এই ভাড়াটিয়া আর সক্রিয় নন।']);
        }
        if (! $this->canSpeakFor($by, $tenant->flat_id)) {
            throw ValidationException::withMessages(['tenant_id' => 'এটি আপনার ফ্ল্যাটের ভাড়াটিয়া নন।']);
        }
        if (TenantVacateRequest::where('tenant_id', $tenant->id)->where('status', 'PENDING')->exists()) {
            throw ValidationException::withMessages(['tenant_id' => 'এই ভাড়াটিয়ার জন্য একটি আবেদন আগেই জমা আছে — অ্যাডমিনের সিদ্ধান্তের অপেক্ষায়।']);
        }
        if ($endDate < $tenant->start_date->toDateString()) {
            throw ValidationException::withMessages(['end_date' => 'ছাড়ার তারিখ ভাড়া শুরুর তারিখের আগে হতে পারে না।']);
        }

        return TenantVacateRequest::create([
            'flat_id' => $tenant->flat_id, 'tenant_id' => $tenant->id, 'requested_by' => $by->id,
            'end_date' => $endDate, 'note' => $note, 'status' => 'PENDING',
        ]);
    }

    public function approve(TenantVacateRequest $request, User $admin): TenantVacateRequest
    {
        return DB::transaction(function () use ($request, $admin) {
            $request = TenantVacateRequest::whereKey($request->id)->lockForUpdate()->first();
            $this->mustBePending($request);

            $this->occupancy->vacateTenant($request->tenant, $request->end_date->toDateString());
            $request->update(['status' => 'APPROVED', 'decided_by' => $admin->id, 'decided_at' => now()]);

            return $request->fresh();
        });
    }

    public function reject(TenantVacateRequest $request, User $admin, string $reason): TenantVacateRequest
    {
        $this->mustBePending($request);
        $request->update(['status' => 'REJECTED', 'decided_by' => $admin->id, 'decided_at' => now(), 'reject_reason' => $reason]);

        return $request->fresh();
    }

    public function cancel(TenantVacateRequest $request, User $by): TenantVacateRequest
    {
        $this->mustBePending($request);
        $request->update(['status' => 'CANCELLED', 'decided_by' => $by->id, 'decided_at' => now()]);

        return $request->fresh();
    }

    private function mustBePending(TenantVacateRequest $request): void
    {
        if ($request->status !== 'PENDING') {
            throw ValidationException::withMessages(['status' => 'এই আবেদনের সিদ্ধান্ত ইতিমধ্যে হয়ে গেছে।']);
        }
    }
}
