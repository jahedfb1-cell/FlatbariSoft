<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Flat;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase05FlatMasterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\FlatMasterPermissionSeeder::class);

        $user = User::factory()->create(['role' => 'admin']);
        $user->assignRole('admin');

        return $user;
    }

    // ---- Flats -----------------------------------------------------

    public function test_admin_can_create_a_flat(): void
    {
        $building = Building::factory()->create();

        $response = $this->actingAs($this->admin(), 'sanctum')->postJson('/api/masters/flats', [
            'building_id' => $building->id,
            'flat_no' => 'A-1',
            'floor' => '1',
            'size_sqft' => 1200,
        ]);

        $response->assertStatus(201)->assertJson(['flat_no' => 'A-1', 'occupancy_status' => 'EMPTY']);
        $this->assertDatabaseHas('flats', ['flat_no' => 'A-1', 'building_id' => $building->id]);
    }

    public function test_flat_no_must_be_unique_within_a_building(): void
    {
        $building = Building::factory()->create();
        Flat::factory()->create(['building_id' => $building->id, 'flat_no' => 'A-1']);

        $response = $this->actingAs($this->admin(), 'sanctum')->postJson('/api/masters/flats', [
            'building_id' => $building->id,
            'flat_no' => 'A-1',
            'floor' => '1',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('flat_no');
    }

    public function test_manager_can_view_but_not_create_flats(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\FlatMasterPermissionSeeder::class);
        $manager = User::factory()->create(['role' => 'manager']);
        $manager->assignRole('manager');

        $this->actingAs($manager, 'sanctum')->getJson('/api/masters/flats')->assertStatus(200);
        $this->actingAs($manager, 'sanctum')->postJson('/api/masters/flats', [
            'building_id' => Building::factory()->create()->id,
            'flat_no' => 'B-1',
            'floor' => '2',
        ])->assertStatus(403);
    }

    public function test_deleting_a_flat_soft_deletes_it(): void
    {
        $flat = Flat::factory()->create();

        $this->actingAs($this->admin(), 'sanctum')->deleteJson("/api/masters/flats/{$flat->id}")->assertStatus(200);

        $this->assertSoftDeleted('flats', ['id' => $flat->id]);
    }

    // ---- Persons -----------------------------------------------------

    public function test_person_mobile_must_be_valid_bd_format(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')->postJson('/api/masters/persons', [
            'name_bn' => 'রহিম উদ্দিন',
            'mobile' => '12345',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('mobile');
    }

    // ---- Owner assignment -----------------------------------------------------

    public function test_assigning_an_owner_creates_person_and_flat_party(): void
    {
        $flat = Flat::factory()->create();

        $response = $this->actingAs($this->admin(), 'sanctum')->postJson("/api/masters/flats/{$flat->id}/owners", [
            'person' => ['name_bn' => 'করিম সাহেব', 'mobile' => '01711112222'],
            'from_date' => '2026-01-01',
            'is_billing_contact' => true,
        ]);

        $response->assertStatus(201)->assertJson(['role' => 'OWNER']);
        $this->assertDatabaseHas('persons', ['mobile' => '01711112222']);
        $this->assertDatabaseHas('flat_parties', ['flat_id' => $flat->id, 'role' => 'OWNER', 'to_date' => null]);
    }

    public function test_assigning_an_owner_reuses_an_existing_person_by_mobile(): void
    {
        $person = Person::factory()->create(['mobile' => '01711112222']);
        $flatA = Flat::factory()->create();
        $flatB = Flat::factory()->create();

        $this->actingAs($this->admin(), 'sanctum')->postJson("/api/masters/flats/{$flatA->id}/owners", [
            'person' => ['name_bn' => 'ভিন্ন নাম দিলেও', 'mobile' => '01711112222'],
            'from_date' => '2026-01-01',
        ]);
        $this->actingAs($this->admin(), 'sanctum')->postJson("/api/masters/flats/{$flatB->id}/owners", [
            'person' => ['name_bn' => 'ভিন্ন নাম দিলেও', 'mobile' => '01711112222'],
            'from_date' => '2026-01-01',
        ]);

        $this->assertSame(1, Person::where('mobile', '01711112222')->count());
        $this->assertSame(2, $person->fresh()->flatParties()->count());
    }

    public function test_ending_ownership_sets_to_date_without_deleting_the_row(): void
    {
        $flat = Flat::factory()->create();
        $party = $this->actingAs($this->admin(), 'sanctum')->postJson("/api/masters/flats/{$flat->id}/owners", [
            'person' => ['name_bn' => 'করিম সাহেব', 'mobile' => '01711112222'],
            'from_date' => '2026-01-01',
        ])->json();

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson("/api/masters/flats/{$flat->id}/owners/{$party['id']}", ['to_date' => '2026-06-01']);

        $response->assertStatus(200)->assertJson(['to_date' => '2026-06-01']);
        $this->assertDatabaseHas('flat_parties', ['id' => $party['id'], 'to_date' => '2026-06-01']);
    }

    // ---- Tenant assignment -----------------------------------------------------

    public function test_assigning_a_tenant_creates_tenant_and_flat_party_and_updates_occupancy(): void
    {
        $flat = Flat::factory()->create(['occupancy_status' => 'EMPTY']);

        $response = $this->actingAs($this->admin(), 'sanctum')->postJson("/api/masters/flats/{$flat->id}/tenants", [
            'person' => ['name_bn' => 'ভাড়াটিয়া মিয়া', 'mobile' => '01911112222'],
            'start_date' => '2026-01-01',
            'monthly_rent' => 15000,
            'security_deposit' => 30000,
        ]);

        $response->assertStatus(201)->assertJson(['status' => 'ACTIVE']);
        $this->assertSame('TENANTED', $flat->fresh()->occupancy_status);
        $this->assertDatabaseHas('flat_parties', ['flat_id' => $flat->id, 'role' => 'TENANT', 'is_billing_contact' => 1]);
    }

    public function test_flat_cannot_have_two_active_tenants(): void
    {
        $flat = Flat::factory()->create();
        $this->actingAs($this->admin(), 'sanctum')->postJson("/api/masters/flats/{$flat->id}/tenants", [
            'person' => ['name_bn' => 'প্রথম ভাড়াটিয়া', 'mobile' => '01911112222'],
            'start_date' => '2026-01-01',
        ]);

        $response = $this->actingAs($this->admin(), 'sanctum')->postJson("/api/masters/flats/{$flat->id}/tenants", [
            'person' => ['name_bn' => 'দ্বিতীয় ভাড়াটিয়া', 'mobile' => '01911113333'],
            'start_date' => '2026-02-01',
        ]);

        $response->assertStatus(422);
    }

    public function test_vacating_a_tenant_closes_flat_party_and_empties_the_flat(): void
    {
        $flat = Flat::factory()->create();
        $tenant = $this->actingAs($this->admin(), 'sanctum')->postJson("/api/masters/flats/{$flat->id}/tenants", [
            'person' => ['name_bn' => 'ভাড়াটিয়া মিয়া', 'mobile' => '01911112222'],
            'start_date' => '2026-01-01',
        ])->json();

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/masters/tenants/{$tenant['id']}/vacate", ['end_date' => '2026-06-01']);

        $response->assertStatus(200)->assertJson(['status' => 'VACATED']);
        $this->assertSame('EMPTY', $flat->fresh()->occupancy_status);
        $this->assertDatabaseHas('flat_parties', [
            'flat_id' => $flat->id, 'role' => 'TENANT', 'to_date' => '2026-06-01',
        ]);
    }

    // ---- users.person_id FK (deferred from Phase 0.3) -----------------------------------------------------

    public function test_user_can_link_to_a_person_now_that_the_fk_exists(): void
    {
        $person = Person::factory()->create();
        $user = User::factory()->create(['person_id' => $person->id, 'role' => 'flat_owner']);

        $this->assertSame($person->id, $user->person->id);
        $this->assertSame($user->id, $person->user->id);
    }
}
