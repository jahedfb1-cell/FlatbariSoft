<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase07StaffTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\StaffPermissionSeeder::class);

        $user = User::factory()->create(['role' => 'admin']);
        $user->assignRole('admin');

        return $user;
    }

    public function test_admin_can_create_staff(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')->postJson('/api/masters/staff', [
            'name' => 'Karim Mia',
            'designation' => 'Manager',
            'mobile' => '01711112222',
            'join_date' => '2024-01-01',
            'monthly_salary' => 20000,
        ]);

        $response->assertStatus(201)->assertJson(['name' => 'Karim Mia', 'status' => 'ACTIVE']);
        $this->assertDatabaseHas('staff', ['mobile' => '01711112222']);
    }

    public function test_staff_validation_rejects_bad_mobile(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')->postJson('/api/masters/staff', [
            'name' => 'Karim Mia',
            'designation' => 'Manager',
            'mobile' => '12345',
            'join_date' => '2024-01-01',
            'monthly_salary' => 20000,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('mobile');
    }

    public function test_manager_role_cannot_view_staff_salary_data(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\StaffPermissionSeeder::class);
        $manager = User::factory()->create(['role' => 'manager']);
        $manager->assignRole('manager');

        $this->actingAs($manager, 'sanctum')->getJson('/api/masters/staff')->assertStatus(403);
    }

    public function test_admin_can_update_and_soft_delete_staff(): void
    {
        $admin = $this->admin();
        $staff = Staff::factory()->create();

        $this->actingAs($admin, 'sanctum')->putJson("/api/masters/staff/{$staff->id}", ['monthly_salary' => 25000])
            ->assertStatus(200)->assertJson(['monthly_salary' => '25000.0000']);

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/masters/staff/{$staff->id}")->assertStatus(200);
        $this->assertSoftDeleted('staff', ['id' => $staff->id]);
    }

    public function test_user_can_link_to_staff_now_that_the_fk_exists(): void
    {
        $staff = Staff::factory()->create();
        $user = User::factory()->create(['staff_id' => $staff->id, 'role' => 'manager']);

        $this->assertSame($staff->id, $user->staff->id);
    }
}
