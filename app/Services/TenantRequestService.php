<?php

namespace App\Services;

use App\Models\Flat;
use App\Models\TenantRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The owner (or manager) proposes, the admin decides. Approving is the only moment the tenancy and the tenant's
 * login come into being - through the very same FlatOccupancyService the admin's own "add tenant" button uses.
 */
class TenantRequestService
{
    public function __construct(private readonly FlatOccupancyService $occupancy) {}

    /** @param  array<string, mixed>  $data  validated StoreTenantRequestRequest data */
    public function submit(User $by, array $data): TenantRequest
    {
        $flat = Flat::findOrFail($data['flat_id']);

        if ($flat->tenants()->where('status', 'ACTIVE')->exists()) {
            throw ValidationException::withMessages(['flat_id' => 'এই ফ্ল্যাটে ইতিমধ্যে একজন ভাড়াটিয়া আছে।']);
        }
        if (TenantRequest::where('flat_id', $flat->id)->where('status', 'PENDING')->exists()) {
            throw ValidationException::withMessages(['flat_id' => 'এই ফ্ল্যাটের জন্য একটি আবেদন আগেই জমা আছে — অ্যাডমিনের সিদ্ধান্তের অপেক্ষায়।']);
        }

        return TenantRequest::create($data + ['requested_by' => $by->id, 'status' => 'PENDING']);
    }

    /** The admin accepts: the tenancy and the login are created now. */
    public function approve(TenantRequest $request, User $admin): TenantRequest
    {
        return DB::transaction(function () use ($request, $admin) {
            $request = TenantRequest::whereKey($request->id)->lockForUpdate()->first();
            $this->mustBePending($request);

            $tenant = $this->occupancy->assignTenant(
                $request->flat,
                array_filter(['name_bn' => $request->name_bn, 'name_en' => $request->name_en, 'mobile' => $request->mobile, 'nid' => $request->nid], fn ($v) => $v !== null),
                array_filter([
                    'start_date' => $request->start_date->toDateString(),
                    'occupation' => $request->occupation,
                    'family_members_count' => $request->family_members_count,
                    'monthly_rent' => $request->monthly_rent,
                    'security_deposit' => $request->security_deposit,
                ], fn ($v) => $v !== null),
            );

            $request->update(['status' => 'APPROVED', 'decided_by' => $admin->id, 'decided_at' => now(), 'tenant_id' => $tenant->id]);

            return $request->fresh();
        });
    }

    public function reject(TenantRequest $request, User $admin, string $reason): TenantRequest
    {
        $this->mustBePending($request);
        $request->update(['status' => 'REJECTED', 'decided_by' => $admin->id, 'decided_at' => now(), 'reject_reason' => $reason]);

        return $request->fresh();
    }

    /** The person who made the request can take it back while it is still waiting. */
    public function cancel(TenantRequest $request, User $by): TenantRequest
    {
        $this->mustBePending($request);
        $request->update(['status' => 'CANCELLED', 'decided_by' => $by->id, 'decided_at' => now()]);

        return $request->fresh();
    }

    private function mustBePending(TenantRequest $request): void
    {
        if ($request->status !== 'PENDING') {
            throw ValidationException::withMessages(['status' => 'এই আবেদনের সিদ্ধান্ত ইতিমধ্যে হয়ে গেছে।']);
        }
    }
}
