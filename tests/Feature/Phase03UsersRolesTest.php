<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class Phase03UsersRolesTest extends TestCase
{
    use RefreshDatabase, WithoutModelEvents;

    public function test_role_seeder_creates_the_five_prd_roles(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->assertDatabaseCount('roles', 5);
        foreach (['super_admin', 'admin', 'manager', 'flat_owner', 'tenant'] as $role) {
            $this->assertDatabaseHas('roles', ['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_role_seeder_is_idempotent(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->assertDatabaseCount('roles', 5);
    }

    public function test_user_seeder_creates_super_admin_with_role_assigned(): void
    {
        $this->seed();

        $admin = User::where('mobile', '01700000001')->first();

        $this->assertNotNull($admin);
        $this->assertSame('super_admin', $admin->role);
        $this->assertTrue($admin->hasRole('super_admin'));
    }

    public function test_mobile_number_is_unique(): void
    {
        User::factory()->create(['mobile' => '01711111111']);

        $this->expectException(QueryException::class);
        User::factory()->create(['mobile' => '01711111111']);
    }

    public function test_soft_deleted_user_is_excluded_from_default_queries(): void
    {
        $user = User::factory()->create();
        $user->delete();

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertNull(User::find($user->id));
        $this->assertNotNull(User::withTrashed()->find($user->id));
    }

    public function test_sanctum_authenticated_user_is_checked_against_web_guard_permissions(): void
    {
        // Regression guard: auth:sanctum authenticates the request, but
        // hasPermissionTo()/the `permission:` middleware check against the
        // 'web' guard by default (config('auth.defaults.guard')). Every
        // future protected route stacks auth:sanctum + permission:xxx, so
        // this combination must work end to end.
        Permission::create(['name' => 'phase03.test', 'guard_name' => 'web']);

        Route::middleware(['auth:sanctum', 'permission:phase03.test'])
            ->get('/__test-sanctum-permission-gate', fn () => response()->json(['ok' => true]));

        $user = User::factory()->create();
        $user->givePermissionTo('phase03.test');

        $response = $this->actingAs($user, 'sanctum')->getJson('/__test-sanctum-permission-gate');

        $response->assertStatus(200)->assertJson(['ok' => true]);
    }
}
