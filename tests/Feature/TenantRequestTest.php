<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\FlatParty;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\TenantRequest;
use App\Models\User;
use Database\Seeders\FlatMasterPermissionSeeder;
use Database\Seeders\OperationsPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The owner (or manager) says "this person rents my flat"; only an admin accepts, and that creates the tenancy and login. */
class TenantRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Flat $flat;

    private User $admin;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(FlatMasterPermissionSeeder::class);
        $this->seed(OperationsPermissionSeeder::class);

        $person = Person::factory()->create();
        $this->owner = User::factory()->create(['person_id' => $person->id, 'role' => 'flat_owner']);
        $this->flat = Flat::factory()->create();
        FlatParty::create(['flat_id' => $this->flat->id, 'person_id' => $person->id, 'role' => 'OWNER', 'from_date' => '2026-01-01']);

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->assignRole('admin');
        $this->manager = User::factory()->create(['role' => 'manager']);
        $this->manager->assignRole('manager');
    }

    private function body(array $over = []): array
    {
        return $over + [
            'flat_id' => $this->flat->id, 'name_bn' => 'করিম', 'mobile' => '01912345678', 'nid' => '1234567890',
            'start_date' => '2026-11-01', 'monthly_rent' => 15000, 'security_deposit' => 30000, 'occupation' => 'Engineer',
        ];
    }

    public function test_an_owner_sends_a_request_and_nothing_changes_until_the_admin_accepts(): void
    {
        $before = $this->flat->fresh()->occupancy_status;
        $this->actingAs($this->owner, 'sanctum')->postJson('/api/tenant-requests', $this->body())->assertCreated()->assertJsonPath('status', 'PENDING');

        $this->assertSame(0, Tenant::count());
        $this->assertDatabaseMissing('persons', ['mobile' => '01912345678']);
        $this->assertSame($before, $this->flat->fresh()->occupancy_status);
    }

    public function test_the_admin_accepting_creates_the_tenancy_and_the_login(): void
    {
        $id = $this->actingAs($this->owner, 'sanctum')->postJson('/api/tenant-requests', $this->body())->json('id');

        $res = $this->actingAs($this->admin, 'sanctum')->postJson("/api/tenant-requests/{$id}/approve")->assertOk()->assertJsonPath('status', 'APPROVED');

        $tenant = Tenant::where('flat_id', $this->flat->id)->first();
        $this->assertNotNull($tenant);
        $this->assertSame($tenant->id, $res->json('tenant_id'));
        $this->assertSame('TENANTED', $this->flat->fresh()->occupancy_status);
        $this->assertSame('15000.0000', $tenant->monthly_rent);

        // the tenant can log in (a user exists for their number) with the tenant role
        $login = User::where('person_id', $tenant->person_id)->first();
        $this->assertNotNull($login);
        $this->assertTrue($login->hasRole('tenant'));
        $this->assertSame($this->admin->id, TenantRequest::find($id)->decided_by);
    }

    public function test_a_decided_request_cannot_be_decided_again(): void
    {
        $id = $this->actingAs($this->owner, 'sanctum')->postJson('/api/tenant-requests', $this->body())->json('id');
        $api = $this->actingAs($this->admin, 'sanctum');
        $api->postJson("/api/tenant-requests/{$id}/reject", ['reason' => 'NID does not match'])->assertOk()->assertJsonPath('status', 'REJECTED');

        $api->postJson("/api/tenant-requests/{$id}/approve")->assertStatus(422);
        $this->assertSame(0, Tenant::count());
    }

    public function test_rejecting_needs_a_reason(): void
    {
        $id = $this->actingAs($this->owner, 'sanctum')->postJson('/api/tenant-requests', $this->body())->json('id');
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/tenant-requests/{$id}/reject", [])->assertStatus(422)->assertJsonValidationErrors('reason');
    }

    public function test_an_owner_can_name_only_their_own_flat_but_the_manager_any_flat(): void
    {
        $other = Flat::factory()->create();

        $this->actingAs($this->owner, 'sanctum')->postJson('/api/tenant-requests', $this->body(['flat_id' => $other->id]))->assertStatus(422)->assertJsonValidationErrors('flat_id');
        $this->actingAs($this->manager, 'sanctum')->postJson('/api/tenant-requests', $this->body(['flat_id' => $other->id]))->assertCreated();
    }

    public function test_one_waiting_request_per_flat_and_not_for_a_flat_that_already_has_a_tenant(): void
    {
        $api = $this->actingAs($this->owner, 'sanctum');
        $api->postJson('/api/tenant-requests', $this->body())->assertCreated();
        $api->postJson('/api/tenant-requests', $this->body(['mobile' => '01811111111']))->assertStatus(422)->assertJsonValidationErrors('flat_id');

        $busy = Flat::factory()->create();
        Tenant::create(['flat_id' => $busy->id, 'person_id' => Person::factory()->create()->id, 'start_date' => '2026-01-01', 'status' => 'ACTIVE']);
        $this->actingAs($this->manager, 'sanctum')->postJson('/api/tenant-requests', $this->body(['flat_id' => $busy->id]))->assertStatus(422);
    }

    public function test_fields_are_validated(): void
    {
        $api = $this->actingAs($this->owner, 'sanctum');
        $api->postJson('/api/tenant-requests', $this->body(['mobile' => '12345']))->assertStatus(422)->assertJsonValidationErrors('mobile');
        $api->postJson('/api/tenant-requests', $this->body(['nid' => '12']))->assertStatus(422)->assertJsonValidationErrors('nid');
        $api->postJson('/api/tenant-requests', $this->body(['name_bn' => '']))->assertStatus(422)->assertJsonValidationErrors('name_bn');
    }

    public function test_only_the_admin_can_decide_and_people_see_only_their_own_requests(): void
    {
        $mine = $this->actingAs($this->owner, 'sanctum')->postJson('/api/tenant-requests', $this->body())->json('id');
        $other = Flat::factory()->create();
        $theirs = $this->actingAs($this->manager, 'sanctum')->postJson('/api/tenant-requests', $this->body(['flat_id' => $other->id, 'mobile' => '01711111111']))->json('id');

        $this->actingAs($this->owner, 'sanctum')->postJson("/api/tenant-requests/{$mine}/approve")->assertForbidden();
        $this->actingAs($this->manager, 'sanctum')->postJson("/api/tenant-requests/{$theirs}/approve")->assertForbidden();

        $ownerList = $this->actingAs($this->owner, 'sanctum')->getJson('/api/tenant-requests')->assertOk();
        $this->assertSame([$mine], collect($ownerList->json('requests'))->pluck('id')->all());
        $this->assertFalse($ownerList->json('can_decide'));

        $adminList = $this->actingAs($this->admin, 'sanctum')->getJson('/api/tenant-requests')->assertOk();
        $this->assertCount(2, $adminList->json('requests'));
        $this->assertTrue($adminList->json('can_decide'));
    }

    public function test_the_sender_can_take_a_waiting_request_back_but_not_someone_elses(): void
    {
        $id = $this->actingAs($this->owner, 'sanctum')->postJson('/api/tenant-requests', $this->body())->json('id');

        $this->actingAs($this->manager, 'sanctum')->postJson("/api/tenant-requests/{$id}/cancel")->assertForbidden();
        $this->actingAs($this->owner, 'sanctum')->postJson("/api/tenant-requests/{$id}/cancel")->assertOk()->assertJsonPath('status', 'CANCELLED');
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/tenant-requests/{$id}/approve")->assertStatus(422);
    }
}
