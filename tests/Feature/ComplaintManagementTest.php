<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Flat;
use App\Models\Staff;
use App\Models\User;
use Database\Seeders\GovernancePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The office side of complaints: see them with their flat, assign, finish with a note, re-open. */
class ComplaintManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(GovernancePermissionSeeder::class);
        $this->manager = User::factory()->create(['role' => 'manager']);
        $this->manager->assignRole('manager');
    }

    private function complaint(array $over = []): Complaint
    {
        return Complaint::create($over + [
            'flat_id' => Flat::factory()->create()->id, 'category' => 'Lift', 'description' => 'Lift stuck on 3rd floor',
            'priority' => 'MEDIUM', 'status' => 'OPEN',
        ]);
    }

    public function test_list_shows_flat_counts_and_puts_urgent_open_work_first(): void
    {
        $done = $this->complaint(['status' => 'RESOLVED']);
        $low = $this->complaint(['priority' => 'LOW']);
        $high = $this->complaint(['priority' => 'HIGH', 'category' => 'Fire alarm']);

        $res = $this->actingAs($this->manager, 'sanctum')->getJson('/api/complaints')->assertOk();

        $this->assertSame([$high->id, $low->id, $done->id], collect($res->json('complaints'))->pluck('id')->all());
        $this->assertSame(2, $res->json('counts.OPEN'));
        $this->assertSame(1, $res->json('counts.RESOLVED'));
        $this->assertSame($high->flat->flat_no, $res->json('complaints.0.flat_no'));
    }

    public function test_filter_and_search(): void
    {
        $this->complaint(['category' => 'Plumbing', 'description' => 'Leaking tap']);
        $lift = $this->complaint(['category' => 'Lift']);

        $by = fn ($qs) => collect($this->actingAs($this->manager, 'sanctum')->getJson("/api/complaints?{$qs}")->json('complaints'))->pluck('id')->all();

        $this->assertSame([$lift->id], $by('q=Lift'));
        $this->assertSame([$lift->id], $by('q='.$lift->flat->flat_no));
        $this->assertCount(1, $by('q=tap'));
        $this->assertCount(0, $by('status=CLOSED'));
    }

    public function test_assign_finish_with_a_note_then_reopen(): void
    {
        $c = $this->complaint();
        $staff = Staff::create(['name' => 'Karim', 'designation' => 'Electrician', 'mobile' => '01711111111', 'monthly_salary' => 10000, 'join_date' => '2026-01-01', 'status' => 'ACTIVE']);

        $this->actingAs($this->manager, 'sanctum')->putJson("/api/complaints/{$c->id}/status", ['status' => 'IN_PROGRESS', 'assigned_to' => $staff->id])
            ->assertOk()->assertJsonPath('assignee', 'Karim')->assertJsonPath('resolved_at', null);

        $done = $this->actingAs($this->manager, 'sanctum')->putJson("/api/complaints/{$c->id}/status", [
            'status' => 'RESOLVED', 'assigned_to' => $staff->id, 'resolution_note' => 'Motor replaced',
        ])->assertOk();
        $this->assertSame('Motor replaced', $done->json('resolution_note'));
        $this->assertNotNull($done->json('resolved_at'));
        $this->assertNotNull($done->json('resolved_by'));

        $again = $this->actingAs($this->manager, 'sanctum')->putJson("/api/complaints/{$c->id}/status", ['status' => 'OPEN'])->assertOk();
        $this->assertNull($again->json('resolved_at'));
        $this->assertNull($again->json('resolved_by'));
    }

    public function test_residents_cannot_reach_the_office_list(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant']);
        $this->actingAs($tenant, 'sanctum')->getJson('/api/complaints')->assertForbidden();
        $this->actingAs($tenant, 'sanctum')->putJson('/api/complaints/'.$this->complaint()->id.'/status', ['status' => 'RESOLVED'])->assertForbidden();
    }
}
