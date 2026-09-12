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

    public function test_user_can_change_their_own_password(): void
    {
        $user = User::factory()->create(['email' => 'admin@greenvalley.com', 'password' => Hash::make('old-password')]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/auth/change-password', [
            'current_password' => 'old-password',
            'new_password' => 'new-password-123',
            'new_password_confirmation' => 'new-password-123',
        ]);

        $response->assertStatus(200);
        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_change_password_rejects_wrong_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/auth/change-password', [
            'current_password' => 'not-the-current-one',
            'new_password' => 'new-password-123',
            'new_password_confirmation' => 'new-password-123',
        ]);

        $response->assertStatus(422);
        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_change_password_requires_confirmation_to_match(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/auth/change-password', [
            'current_password' => 'old-password',
            'new_password' => 'new-password-123',
            'new_password_confirmation' => 'does-not-match',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('new_password');
    }

    public function test_change_password_enforces_minimum_length(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/auth/change-password', [
            'current_password' => 'old-password',
            'new_password' => 'short',
            'new_password_confirmation' => 'short',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('new_password');
    }

    public function test_change_password_requires_authentication(): void
    {
        $response = $this->postJson('/api/auth/change-password', [
            'current_password' => 'old-password',
            'new_password' => 'new-password-123',
            'new_password_confirmation' => 'new-password-123',
        ]);

        $response->assertStatus(401);
    }

    public function test_changing_password_revokes_other_tokens_but_keeps_the_current_one(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);
        $otherToken = $user->createToken('other-device');
        $currentToken = $user->createToken('this-device');

        $this->withHeader('Authorization', "Bearer {$currentToken->plainTextToken}")
            ->postJson('/api/auth/change-password', [
                'current_password' => 'old-password',
                'new_password' => 'new-password-123',
                'new_password_confirmation' => 'new-password-123',
            ])
            ->assertStatus(200);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $otherToken->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $currentToken->accessToken->id]);
    }
}
