<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\Flat;
use App\Models\User;
use Database\Seeders\OperationsPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Who changed what: written automatically, never with passwords, readable only with audit.view. */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(OperationsPermissionSeeder::class);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->assignRole('admin');
    }

    public function test_changes_are_recorded_with_who_before_and_after(): void
    {
        $this->actingAs($this->admin, 'sanctum');
        $flat = Flat::factory()->create(['service_charge_rate' => 3000]);
        $flat->update(['service_charge_rate' => 3500]);

        $update = AuditLog::where('auditable_type', 'Flat')->where('auditable_id', $flat->id)->where('event', 'updated')->first();
        $this->assertSame($this->admin->id, $update->user_id);
        $this->assertEquals(3000, $update->old_values['service_charge_rate']);
        $this->assertEquals(3500, $update->new_values['service_charge_rate']);
        $this->assertTrue(AuditLog::where('auditable_id', $flat->id)->where('event', 'created')->exists());
    }

    public function test_deletes_are_recorded_and_passwords_never_are(): void
    {
        $this->actingAs($this->admin, 'sanctum');
        $user = User::factory()->create(['role' => 'tenant']);
        $user->update(['password' => 'a-new-secret-password']);
        $c = Complaint::create(['flat_id' => Flat::factory()->create()->id, 'category' => 'x', 'description' => 'y', 'priority' => 'LOW', 'status' => 'OPEN']);
        $c->delete();

        $this->assertTrue(AuditLog::where('auditable_type', 'Complaint')->where('event', 'deleted')->exists());
        $this->assertStringNotContainsString('a-new-secret-password', AuditLog::pluck('new_values')->toJson());
        $this->assertStringNotContainsString('password', AuditLog::where('auditable_type', 'User')->get()->toJson());
    }

    public function test_only_people_with_the_permission_can_read_the_log(): void
    {
        $this->actingAs($this->admin, 'sanctum');
        Flat::factory()->create();

        $res = $this->getJson('/api/audit-logs?model=Flat')->assertOk();
        $this->assertGreaterThan(0, $res->json('total'));
        $this->assertContains('Flat', $res->json('models'));

        $tenant = User::factory()->create(['role' => 'tenant']);
        $this->actingAs($tenant, 'sanctum')->getJson('/api/audit-logs')->assertForbidden();
    }
}
