<?php

namespace Tests\Feature;

use App\Models\Otp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Phase04OtpAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_otp_request_rejects_unknown_mobile(): void
    {
        $response = $this->postJson('/api/auth/otp/request', ['mobile' => '01799999999']);

        $response->assertStatus(404);
    }

    public function test_otp_request_rejects_inactive_account(): void
    {
        User::factory()->inactive()->create(['mobile' => '01711112222']);

        $response = $this->postJson('/api/auth/otp/request', ['mobile' => '01711112222']);

        $response->assertStatus(404);
    }

    public function test_otp_request_validates_mobile_format(): void
    {
        $response = $this->postJson('/api/auth/otp/request', ['mobile' => '12345']);

        $response->assertStatus(422)->assertJsonValidationErrors('mobile');
    }

    public function test_otp_request_returns_dev_otp_in_testing_env_and_creates_record(): void
    {
        $user = User::factory()->create(['mobile' => '01711112222']);

        $response = $this->postJson('/api/auth/otp/request', ['mobile' => '01711112222']);

        $response->assertStatus(200)->assertJsonStructure(['message', 'dev_otp']);
        $this->assertNotNull($response->json('dev_otp'));
        $this->assertDatabaseHas('otps', ['mobile' => $user->mobile]);
    }

    public function test_otp_request_is_rate_limited(): void
    {
        User::factory()->create(['mobile' => '01711112222']);

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/auth/otp/request', ['mobile' => '01711112222'])->assertStatus(200);
        }

        $this->postJson('/api/auth/otp/request', ['mobile' => '01711112222'])->assertStatus(429);
    }

    public function test_verify_with_correct_code_issues_token_and_updates_last_login(): void
    {
        $user = User::factory()->create(['mobile' => '01711112222', 'last_login' => null]);
        $otp = $this->postJson('/api/auth/otp/request', ['mobile' => '01711112222'])->json('dev_otp');

        $response = $this->postJson('/api/auth/otp/verify', ['mobile' => '01711112222', 'code' => $otp]);

        $response->assertStatus(200)->assertJsonStructure(['token', 'user' => ['id', 'mobile', 'roles', 'permissions']]);
        $this->assertNotNull($user->fresh()->last_login);
    }

    public function test_verify_with_wrong_code_fails_and_increments_attempts(): void
    {
        User::factory()->create(['mobile' => '01711112222']);
        $this->postJson('/api/auth/otp/request', ['mobile' => '01711112222']);

        $response = $this->postJson('/api/auth/otp/verify', ['mobile' => '01711112222', 'code' => '000000']);

        $response->assertStatus(422);
        $this->assertSame(1, Otp::where('mobile', '01711112222')->first()->attempts);
    }

    public function test_verify_fails_after_expiry(): void
    {
        User::factory()->create(['mobile' => '01711112222']);
        $otp = $this->postJson('/api/auth/otp/request', ['mobile' => '01711112222'])->json('dev_otp');

        $this->travel(config('otp.expiry_minutes') + 1)->minutes();

        $response = $this->postJson('/api/auth/otp/verify', ['mobile' => '01711112222', 'code' => $otp]);

        $response->assertStatus(422);
    }

    public function test_verify_locks_out_after_max_attempts(): void
    {
        User::factory()->create(['mobile' => '01711112222']);
        $otp = $this->postJson('/api/auth/otp/request', ['mobile' => '01711112222'])->json('dev_otp');

        for ($i = 0; $i < config('otp.max_attempts'); $i++) {
            $this->postJson('/api/auth/otp/verify', ['mobile' => '01711112222', 'code' => '000000']);
        }

        // Even the genuinely correct code is now refused — attempts are spent.
        $response = $this->postJson('/api/auth/otp/verify', ['mobile' => '01711112222', 'code' => $otp]);

        $response->assertStatus(422);
    }

    public function test_requesting_a_new_otp_invalidates_the_previous_one(): void
    {
        User::factory()->create(['mobile' => '01711112222']);
        $firstOtp = $this->postJson('/api/auth/otp/request', ['mobile' => '01711112222'])->json('dev_otp');
        $this->postJson('/api/auth/otp/request', ['mobile' => '01711112222']);

        $response = $this->postJson('/api/auth/otp/verify', ['mobile' => '01711112222', 'code' => $firstOtp]);

        $response->assertStatus(422);
    }

    public function test_logout_revokes_the_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $tokenId = $user->tokens()->first()->id;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout')
            ->assertStatus(200);

        // Asserted directly against the DB rather than with a second
        // request: Laravel's testing harness caches the resolved user on
        // the (stateless, request-lifetime-scoped in production) sanctum
        // guard instance for the rest of the test method, so a follow-up
        // getJson() would misleadingly still look "authenticated" here even
        // though the token row — and therefore a real second HTTP request —
        // is genuinely gone.
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }

    public function test_me_reports_roles_and_permissions(): void
    {
        Role::create(['name' => 'manager', 'guard_name' => 'web']);
        $user = User::factory()->create(['role' => 'manager']);
        $user->assignRole('manager');

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/auth/me');

        $response->assertStatus(200)->assertJson([
            'mobile' => $user->mobile,
            'roles' => ['manager'],
        ]);
    }
}
