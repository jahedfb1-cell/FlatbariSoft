<?php

namespace App\Services;

use App\Models\Flat;
use App\Models\FlatParty;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Assigning/ending an owner or tenant touches three things together
 * (persons, flat_parties, and — for tenants — the tenants table and the
 * flat's occupancy_status), so each operation runs as one transaction.
 */
class FlatOccupancyService
{
    public function __construct(private readonly PersonService $personService) {}

    public function assignOwner(Flat $flat, array $personData, string $fromDate, bool $isBillingContact = false): FlatParty
    {
        return DB::transaction(function () use ($flat, $personData, $fromDate, $isBillingContact) {
            $person = $this->personService->findOrCreate($personData);

            return FlatParty::create([
                'flat_id' => $flat->id,
                'person_id' => $person->id,
                'role' => 'OWNER',
                'from_date' => $fromDate,
                'is_billing_contact' => $isBillingContact,
            ]);
        });
    }

    public function endOwnership(FlatParty $party, ?string $toDate = null): FlatParty
    {
        if ($party->role !== 'OWNER') {
            throw ValidationException::withMessages(['role' => 'এটি মালিকানার রেকর্ড নয়।']);
        }

        $party->update(['to_date' => $toDate ?? now()->toDateString()]);

        return $party;
    }

    public function assignTenant(Flat $flat, array $personData, array $tenantData): Tenant
    {
        return DB::transaction(function () use ($flat, $personData, $tenantData) {
            if ($flat->tenants()->where('status', 'ACTIVE')->exists()) {
                throw ValidationException::withMessages([
                    'flat_id' => 'এই ফ্ল্যাটে ইতিমধ্যে একজন সক্রিয় ভাড়াটিয়া আছে — আগে তাকে ভ্যাকেট করুন।',
                ]);
            }

            $person = $this->personService->findOrCreate($personData);

            $tenant = Tenant::create([
                'flat_id' => $flat->id,
                'person_id' => $person->id,
                'status' => 'ACTIVE',
                ...$tenantData,
            ]);

            FlatParty::create([
                'flat_id' => $flat->id,
                'person_id' => $person->id,
                'role' => 'TENANT',
                'from_date' => $tenant->start_date,
                // The tenant is, by default, who the gas bill is addressed
                // to (BR: payer_type OWNER/TENANT resolves to whichever
                // party is billing contact for that charge type).
                'is_billing_contact' => true,
            ]);

            $flat->update(['occupancy_status' => 'TENANTED']);

            return $tenant;
        });
    }

    public function vacateTenant(Tenant $tenant, ?string $endDate = null): Tenant
    {
        return DB::transaction(function () use ($tenant, $endDate) {
            $endDate ??= now()->toDateString();

            $tenant->update(['status' => 'VACATED', 'end_date' => $endDate]);

            FlatParty::where('flat_id', $tenant->flat_id)
                ->where('person_id', $tenant->person_id)
                ->where('role', 'TENANT')
                ->whereNull('to_date')
                ->update(['to_date' => $endDate]);

            // Defaults to EMPTY — an admin who knows the owner is moving in
            // instead sets OWNER_OCCUPIED explicitly via the flat update form.
            $tenant->flat->update(['occupancy_status' => 'EMPTY']);

            return $tenant;
        });
    }
}
