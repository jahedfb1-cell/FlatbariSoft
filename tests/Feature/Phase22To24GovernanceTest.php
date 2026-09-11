<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Flat;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class Phase22To24GovernanceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\GovernancePermissionSeeder::class);
        $user = User::factory()->create(['role' => 'admin']);
        $user->assignRole('admin');

        return $user;
    }

    // ---- 2.2 Assets -----------------------------------------------------

    public function test_asset_with_past_next_service_date_is_flagged_overdue(): void
    {
        $overdue = Asset::create(['name' => 'Lift 1', 'category' => 'LIFT', 'next_service_date' => now()->subDay()->toDateString(), 'status' => 'ACTIVE']);
        $upcoming = Asset::create(['name' => 'Lift 2', 'category' => 'LIFT', 'next_service_date' => now()->addMonth()->toDateString(), 'status' => 'ACTIVE']);

        $this->assertTrue($overdue->isServiceOverdue());
        $this->assertFalse($upcoming->isServiceOverdue());
    }

    public function test_admin_can_add_asset_service_log(): void
    {
        $admin = $this->admin();
        $asset = Asset::create(['name' => 'Pump 1', 'category' => 'PUMP', 'status' => 'ACTIVE']);

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/assets/{$asset->id}/service-logs", [
            'service_date' => '2026-08-01', 'description' => 'Routine check', 'cost' => 500,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('asset_service_logs', ['asset_id' => $asset->id, 'cost' => 500]);
    }

    // ---- 2.3 Meetings & Resolutions -----------------------------------------------------

    public function test_special_levy_construction_due_requires_a_resolution(): void
    {
        $admin = $this->admin();
        $flat = Flat::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/construction-dues', [
            'flat_id' => $flat->id, 'category' => 'উন্নয়ন তহবিল', 'source_type' => 'LEVY_RESOLUTION',
            'total_amount' => 10000, 'installments' => [['due_date' => '2026-09-01', 'amount' => 10000]],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('resolution_id');
    }

    public function test_special_levy_succeeds_once_linked_to_a_resolution(): void
    {
        $admin = $this->admin();
        $flat = Flat::factory()->create();
        $meeting = Meeting::create(['title' => 'AGM 2026', 'meeting_date' => '2026-01-01', 'status' => 'HELD']);
        $resolution = $meeting->resolutions()->create(['resolution_no' => 'RES-1-001', 'description' => 'Approve levy', 'status' => 'DONE']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/construction-dues', [
            'flat_id' => $flat->id, 'category' => 'উন্নয়ন তহবিল', 'source_type' => 'LEVY_RESOLUTION',
            'resolution_id' => $resolution->id, 'total_amount' => 10000,
            'installments' => [['due_date' => '2026-09-01', 'amount' => 10000]],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('construction_dues', ['resolution_id' => $resolution->id]);
    }

    public function test_initial_contract_due_does_not_require_a_resolution(): void
    {
        $admin = $this->admin();
        $flat = Flat::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/construction-dues', [
            'flat_id' => $flat->id, 'category' => 'Initial cost', 'source_type' => 'INITIAL_CONTRACT',
            'total_amount' => 10000, 'installments' => [['due_date' => '2026-09-01', 'amount' => 10000]],
        ]);

        $response->assertStatus(201);
    }

    public function test_resolution_numbers_increment_per_meeting(): void
    {
        $admin = $this->admin();
        $meeting = Meeting::create(['title' => 'AGM 2026', 'meeting_date' => '2026-01-01', 'status' => 'HELD']);

        $this->actingAs($admin, 'sanctum')->postJson("/api/meetings/{$meeting->id}/resolutions", ['description' => 'First'])
            ->assertJson(['resolution_no' => 'RES-'.$meeting->id.'-001']);
        $this->actingAs($admin, 'sanctum')->postJson("/api/meetings/{$meeting->id}/resolutions", ['description' => 'Second'])
            ->assertJson(['resolution_no' => 'RES-'.$meeting->id.'-002']);
    }

    // ---- 2.4 Complaints -----------------------------------------------------

    public function test_manager_can_submit_and_update_a_complaint(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\GovernancePermissionSeeder::class);
        $manager = User::factory()->create(['role' => 'manager']);
        $manager->assignRole('manager');
        $flat = Flat::factory()->create();

        $create = $this->actingAs($manager, 'sanctum')->postJson('/api/complaints', [
            'flat_id' => $flat->id, 'category' => 'Plumbing', 'description' => 'Leaking pipe',
        ]);
        $create->assertStatus(201)->assertJson(['status' => 'OPEN']);

        $id = $create->json('id');
        $update = $this->actingAs($manager, 'sanctum')->putJson("/api/complaints/{$id}/status", ['status' => 'RESOLVED']);
        $update->assertStatus(200)->assertJson(['status' => 'RESOLVED']);
        $this->assertNotNull($update->json('resolved_at'));
    }
}
