<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * The tenant's KYC record. Saving rewrites the whole form in one transaction: the person's identity
 * (name, NID, e-mail, address), the tenancy details, and the family list (replaced as a set, so a removed
 * row really goes). The form counts as submitted once every required field is in; the family size on the
 * tenancy always follows the list.
 */
class TenantKycService
{
    /** The fields that must be filled for the KYC to count as complete. */
    private const REQUIRED = ['nid', 'address', 'occupation', 'emergency_name', 'emergency_phone'];

    /** @param  array<string, mixed>  $data  validated TenantKycRequest data */
    public function save(Tenant $tenant, array $data): Tenant
    {
        return DB::transaction(function () use ($tenant, $data) {
            $tenant->person->update([
                'name_bn' => $data['person']['name_bn'],
                'name_en' => $data['person']['name_en'] ?? null,
                'nid' => $data['person']['nid'],
                'alt_mobile' => $data['person']['alt_mobile'] ?? null,
                'email' => $data['person']['email'] ?? null,
                'address' => $data['person']['address'],
            ]);

            $family = $data['family_members'] ?? [];

            $tenant->update([
                'occupation' => $data['occupation'],
                'workplace' => $data['workplace'] ?? null,
                'emergency_name' => $data['emergency_name'],
                'emergency_phone' => $data['emergency_phone'],
                'previous_address' => $data['previous_address'] ?? null,
                'reference_name' => $data['reference_name'] ?? null,
                'reference_phone' => $data['reference_phone'] ?? null,
                'other_info' => $data['other_info'] ?? null,
                'family_members_count' => count($family),
                'kyc_submitted_at' => now(),
            ]);

            $tenant->familyMembers()->delete();
            foreach ($family as $member) {
                $tenant->familyMembers()->create([
                    'name' => $member['name'],
                    'relation' => $member['relation'],
                    'age' => $member['age'] ?? null,
                    'nid' => $member['nid'] ?? null,
                    'mobile' => $member['mobile'] ?? null,
                    'occupation' => $member['occupation'] ?? null,
                ]);
            }

            return $tenant->fresh(['person', 'familyMembers']);
        });
    }

    /**
     * The form as the screen needs it, plus which required fields are still empty.
     *
     * @return array<string, mixed>
     */
    public function present(Tenant $tenant): array
    {
        $tenant->loadMissing('person', 'familyMembers', 'flat');
        $p = $tenant->person;

        $values = [
            'nid' => $p->nid, 'address' => $p->address, 'occupation' => $tenant->occupation,
            'emergency_name' => $tenant->emergency_name, 'emergency_phone' => $tenant->emergency_phone,
        ];
        $missing = array_values(array_filter(self::REQUIRED, fn ($k) => trim((string) ($values[$k] ?? '')) === ''));

        return [
            'tenant_id' => $tenant->id,
            'flat_id' => $tenant->flat_id,
            'flat_no' => $tenant->flat?->flat_no,
            'status' => $tenant->status,
            'start_date' => $tenant->start_date?->toDateString(),
            'monthly_rent' => $tenant->monthly_rent,
            'complete' => $missing === [],
            'missing' => $missing,
            'submitted_at' => $tenant->kyc_submitted_at?->toIso8601String(),
            'person' => [
                'name_bn' => $p->name_bn, 'name_en' => $p->name_en, 'mobile' => $p->mobile, 'alt_mobile' => $p->alt_mobile,
                'nid' => $p->nid, 'email' => $p->email, 'address' => $p->address,
            ],
            'occupation' => $tenant->occupation,
            'workplace' => $tenant->workplace,
            'emergency_name' => $tenant->emergency_name,
            'emergency_phone' => $tenant->emergency_phone,
            'previous_address' => $tenant->previous_address,
            'reference_name' => $tenant->reference_name,
            'reference_phone' => $tenant->reference_phone,
            'other_info' => $tenant->other_info,
            'family_members' => $tenant->familyMembers->map(fn ($m) => [
                'name' => $m->name, 'relation' => $m->relation, 'age' => $m->age, 'nid' => $m->nid, 'mobile' => $m->mobile, 'occupation' => $m->occupation,
            ])->values(),
        ];
    }
}
