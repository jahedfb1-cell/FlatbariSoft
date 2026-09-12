<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Owner-requested second login path alongside mobile+OTP (Phase 04):
 * email + password, for admin-side accounts. Flat_owner/tenant accounts
 * still only ever get mobile+OTP — this never replaces that.
 */
class EmailPasswordLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_correct_credentials_issues_token_and_updates_last_login(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@greenvalley.com',
            'password' => Hash::make('secret123'),
            'last_login' => null,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@greenvalley.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)->assertJsonStructure(['token', 'user' => ['id', 'email', 'roles', 'permissions']]);
        $this->assertNotNull($user->fresh()->last_login);
    }

    public function test_login_rejects_wrong_password(): void
    {
        User::factory()->create(['email' => 'admin@greenvalley.com', 'password' => Hash::make('secret123')]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@greenvalley.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
    }

    public function test_login_rejects_unknown_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'nobody@greenvalley.com',
            'password' => 'whatever',
        ]);

        $response->assertStatus(422);
    }

    public function test_login_rejects_inactive_account(): void
    {
        User::factory()->inactive()->create([
            'email' => 'admin@greenvalley.com',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@greenvalley.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(422);
    }

    public function test_login_validates_required_fields(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertStatus(422)->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_is_rate_limited(): void
    {
        User::factory()->create(['email' => 'admin@greenvalley.com', 'password' => Hash::make('secret123')]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'admin@greenvalley.com', 'password' => 'wrong'])
                ->assertStatus(422);
        }

        $this->postJson('/api/auth/login', ['email' => 'admin@greenvalley.com', 'password' => 'secret123'])
            ->assertStatus(429);
    }

    public function test_a_user_with_no_email_cannot_use_this_route(): void
    {
        User::factory()->create(['email' => null, 'password' => Hash::make('secret123')]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'not-set@greenvalley.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(422);
    }
}
