<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Phase01FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sanctum_blocks_unauthenticated_requests(): void
    {
        $response = $this->getJson('/api/ping');

        $response->assertStatus(401);
    }

    public function test_sanctum_returns_json_401_even_without_accept_header(): void
    {
        // Regression test: a plain browser/curl request (no explicit
        // Accept: application/json header) must still get a JSON 401,
        // not a crash from Laravel trying to redirect to a nonexistent
        // 'login' route — this app has no web auth, only Sanctum tokens.
        $response = $this->get('/api/ping');

        $response->assertStatus(401)->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_sanctum_allows_authenticated_requests(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/ping');

        $response->assertStatus(200)
            ->assertJson(['ok' => true, 'user_id' => $user->id]);
    }

    public function test_permission_middleware_alias_blocks_user_without_permission(): void
    {
        Permission::create(['name' => 'test.access', 'guard_name' => 'web']);

        \Illuminate\Support\Facades\Route::middleware(['web', 'permission:test.access'])
            ->get('/__test-permission-gate', fn () => response()->json(['ok' => true]));

        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/__test-permission-gate');

        $response->assertStatus(403);
    }

    public function test_permission_middleware_alias_allows_user_with_permission(): void
    {
        $permission = Permission::create(['name' => 'test.access', 'guard_name' => 'web']);
        $role = Role::create(['name' => 'tester', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        \Illuminate\Support\Facades\Route::middleware(['web', 'permission:test.access'])
            ->get('/__test-permission-gate', fn () => response()->json(['ok' => true]));

        $user = User::factory()->create();
        $user->assignRole($role);

        $response = $this->actingAs($user)->getJson('/__test-permission-gate');

        $response->assertStatus(200)->assertJson(['ok' => true]);
    }
}
