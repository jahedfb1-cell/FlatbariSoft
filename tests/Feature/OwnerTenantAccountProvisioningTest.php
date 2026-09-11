<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\Person;
use App\Models\User;
use App\Services\FlatOccupancyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Caught during manual Phase 4.4 owner-portal verification: nothing in the
 * "assign owner"/"assign tenant" flow ever created a `users` row, so a real
 * owner/tenant had no way to ever log into the portal that was just built
 * for them. Fixed in PersonService::ensureUserAccount(), called from both
 * FlatOccupancyService::assignOwner() and assignTenant().
 */
class OwnerTenantAccountProvisioningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_assigning_an_owner_creates_a_login_account(): void
    {
        $flat = Flat::factory()->create();
        $service = app(FlatOccupancyService::class);

        $service->assignOwner($flat, ['name_bn' => 'মালিক', 'mobile' => '01711112222'], '2026-01-01');

        $person = Person::where('mobile', '01711112222')->first();
        $user = User::where('person_id', $person->id)->first();

        $this->assertNotNull($user);
        $this->assertSame('flat_owner', $user->role);
        $this->assertTrue($user->hasRole('flat_owner'));
    }

    public function test_assigning_a_tenant_creates_a_login_account(): void
    {
        $flat = Flat::factory()->create();
        $service = app(FlatOccupancyService::class);

        $service->assignTenant($flat, ['name_bn' => 'ভাড়াটিয়া', 'mobile' => '01911112222'], ['start_date' => '2026-01-01']);

        $person = Person::where('mobile', '01911112222')->first();
        $user = User::where('person_id', $person->id)->first();

        $this->assertNotNull($user);
        $this->assertSame('tenant', $user->role);
        $this->assertTrue($user->hasRole('tenant'));
    }

    public function test_reusing_an_existing_person_does_not_duplicate_their_account(): void
    {
        $flatA = Flat::factory()->create();
        $flatB = Flat::factory()->create();
        $service = app(FlatOccupancyService::class);

        $service->assignOwner($flatA, ['name_bn' => 'মালিক', 'mobile' => '01711112222'], '2026-01-01');
        $service->assignOwner($flatB, ['name_bn' => 'মালিক', 'mobile' => '01711112222'], '2026-02-01');

        $this->assertSame(1, User::where('mobile', '01711112222')->count());
    }
}
