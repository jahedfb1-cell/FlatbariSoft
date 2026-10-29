<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\FlatParty;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\TenantVacateRequest;
use App\Models\User;
use Database\Seeders\FlatMasterPermissionSeeder;
use Database\Seeders\OperationsPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The owner (or manager) says "my tenant is leaving"; only an admin accepting it ends the tenancy. */
class TenantVacateRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    private User $manager;

    private Flat $flat;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(FlatMasterPermissionSeeder::class);
        $this->seed(OperationsPermissionSeeder::class);

        $ownerPerson = Person::factory()->create();
        $this->owner = User::factory()->create(['person_id' => $ownerPerson->id, 'role' => 'flat_owner']);
        $this->flat = Flat::factory()->create(['occupancy_status' => 'TENANTED']);
        FlatParty::create(['flat_id' => $this->flat->id, 'person_id' => $ownerPerson->id, 'role' => 'OWNER', 'from_date' => '2026-01-01']);

        $tenantPerson = Person::factory()->create();
        $this->tenant = Tenant::create(['flat_id' => $this->flat->id, 'person_id' => $tenantPerson->id, 'start_date' => '2026-03-01', 'status' => 'ACTIVE']);
        FlatParty::create(['flat_id' => $this->flat->id, 'person_id' => $tenantPerson->id, 'role' => 'TENANT', 'from_date' => '2026-03-01']);

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->assignRole('admin');
        $this->manager = User::factory()->create(['role' => 'manager']);
        $this->manager->assignRole('manager');
    }

    private function body(array $over = []): array
    {
        return $over + ['tenant_id' => $this->tenant->id, 'end_date' => '2026-12-31', 'note' => 'Moving to Chattogram'];
    }

    public function test_an_owner_sends_a_leave_request_and_the_tenancy_stays_until_the_admin_accepts(): void
    {
        $this->actingAs($this->owner, 'sanctum')->postJson('/api/tenant-vacate-requests', $this->body())->assertCreated()->assertJsonPath('status', 'PENDING');

        $this->assertSame('ACTIVE', $this->tenant->fresh()->status);
        $this->assertSame('TENANTED', $this->flat->fresh()->occupancy_status);
    }

    public function test_the_admin_accepting_ends_the_tenancy_and_empties_the_flat(): void
    {
        $id = $this->actingAs($this->owner, 'sanctum')->postJson('/api/tenant-vacate-requests', $this->body())->json('id');

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/tenant-vacate-requests/{$id}/approve")->assertOk()->assertJsonPath('status', 'APPROVED');

        $t = $this->tenant->fresh();
        $this->assertSame('VACATED', $t->status);
        $this->assertSame('2026-12-31', $t->end_date->toDateString());
        $this->assertSame('EMPTY', $this->flat->fresh()->occupancy_status);
        $this->assertNotNull(FlatParty::where('person_id', $t->person_id)->where('role', 'TENANT')->first()->to_date);
    }

    public function test_rejecting_needs_a_reason_and_keeps_the_tenancy(): void
    {
        $id = $this->actingAs($this->owner, 'sanctum')->postJson('/api/tenant-vacate-requests', $this->body())->json('id');
        $api = $this->actingAs($this->admin, 'sanctum');

        $api->postJson("/api/tenant-vacate-requests/{$id}/reject", [])->assertStatus(422);
        $api->postJson("/api/tenant-vacate-requests/{$id}/reject", ['reason' => 'Dues are unpaid'])->assertOk()->assertJsonPath('status', 'REJECTED');
        $this->assertSame('ACTIVE', $this->tenant->fresh()->status);
        $api->postJson("/api/tenant-vacate-requests/{$id}/approve")->assertStatus(422);   // already decided
    }

    public function test_an_owner_can_report_only_their_own_flats_tenant_but_the_manager_any(): void
    {
        $otherFlat = Flat::factory()->create();
        $otherTenant = Tenant::create(['flat_id' => $otherFlat->id, 'person_id' => Person::factory()->create()->id, 'start_date' => '2026-01-01', 'status' => 'ACTIVE']);

        $this->actingAs($this->owner, 'sanctum')->postJson('/api/tenant-vacate-requests', $this->body(['tenant_id' => $otherTenant->id]))->assertStatus(422)->assertJsonValidationErrors('tenant_id');
        $this->actingAs($this->manager, 'sanctum')->postJson('/api/tenant-vacate-requests', $this->body(['tenant_id' => $otherTenant->id]))->assertCreated();
    }

    public function test_the_list_offers_only_the_tenants_the_person_may_report(): void
    {
        Tenant::create(['flat_id' => Flat::factory()->create()->id, 'person_id' => Person::factory()->create()->id, 'start_date' => '2026-01-01', 'status' => 'ACTIVE']);

        $mine = $this->actingAs($this->owner, 'sanctum')->getJson('/api/tenant-vacate-requests')->assertOk();
        $this->assertSame([$this->tenant->id], collect($mine->json('tenants'))->pluck('tenant_id')->all());
        $this->assertFalse($mine->json('can_decide'));

        $this->assertCount(2, $this->actingAs($this->manager, 'sanctum')->getJson('/api/tenant-vacate-requests')->json('tenants'));
    }

    public function test_one_waiting_request_per_tenant_and_the_date_must_make_sense(): void
    {
        $api = $this->actingAs($this->owner, 'sanctum');
        $api->postJson('/api/tenant-vacate-requests', $this->body(['end_date' => '2026-01-01']))->assertStatus(422)->assertJsonValidationErrors('end_date');
        $api->postJson('/api/tenant-vacate-requests', $this->body())->assertCreated();
        $api->postJson('/api/tenant-vacate-requests', $this->body())->assertStatus(422)->assertJsonValidationErrors('tenant_id');
        $api->postJson('/api/tenant-vacate-requests', ['tenant_id' => $this->tenant->id])->assertStatus(422)->assertJsonValidationErrors('end_date');
    }

    public function test_only_the_admin_decides_and_the_sender_can_withdraw(): void
    {
        $id = $this->actingAs($this->owner, 'sanctum')->postJson('/api/tenant-vacate-requests', $this->body())->json('id');

        $this->actingAs($this->owner, 'sanctum')->postJson("/api/tenant-vacate-requests/{$id}/approve")->assertForbidden();
        $this->actingAs($this->manager, 'sanctum')->postJson("/api/tenant-vacate-requests/{$id}/approve")->assertForbidden();
        $this->actingAs($this->manager, 'sanctum')->postJson("/api/tenant-vacate-requests/{$id}/cancel")->assertForbidden();

        $this->actingAs($this->owner, 'sanctum')->postJson("/api/tenant-vacate-requests/{$id}/cancel")->assertOk()->assertJsonPath('status', 'CANCELLED');
        $this->assertSame('CANCELLED', TenantVacateRequest::find($id)->status);
        $this->assertSame('ACTIVE', $this->tenant->fresh()->status);
    }
}
