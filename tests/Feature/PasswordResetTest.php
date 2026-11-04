<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/** Signing in by e-mail or mobile with a password, and getting back in after forgetting it. */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->user = User::factory()->create(['role' => 'admin', 'email' => 'Boss@Gmail.com', 'mobile' => '01712345678', 'password' => 'OldSecret123!']);
        $this->user->assignRole('admin');
    }

    public function test_a_password_login_works_with_the_email_or_the_mobile_in_any_writing(): void
    {
        foreach (['boss@gmail.com', 'BOSS@GMAIL.COM', '01712345678', '+8801712345678', '8801712345678', '017 1234-5678'] as $who) {
            $this->postJson('/api/auth/login', ['identifier' => $who, 'password' => 'OldSecret123!'])->assertOk()->assertJsonStructure(['token']);
        }
        // the old field name still works for existing clients
        $this->postJson('/api/auth/login', ['email' => 'boss@gmail.com', 'password' => 'OldSecret123!'])->assertOk();
        $this->postJson('/api/auth/login', ['identifier' => '01712345678', 'password' => 'wrong'])->assertStatus(422);
        $this->postJson('/api/auth/login', ['identifier' => '01999999999', 'password' => 'OldSecret123!'])->assertStatus(422);
    }

    public function test_an_inactive_account_cannot_sign_in_or_reset(): void
    {
        $this->user->update(['status' => 'INACTIVE']);
        Notification::fake();

        $this->postJson('/api/auth/login', ['identifier' => '01712345678', 'password' => 'OldSecret123!'])->assertStatus(422);
        $this->postJson('/api/auth/forgot-password', ['identifier' => 'boss@gmail.com'])->assertOk();
        Notification::assertNothingSent();
    }

    public function test_forgot_password_by_email_sends_a_link_and_the_link_sets_a_new_password(): void
    {
        Notification::fake();
        $this->postJson('/api/auth/forgot-password', ['identifier' => 'boss@gmail.com'])->assertOk()->assertJsonPath('channel', 'email');

        $token = null;
        Notification::assertSentTo($this->user, ResetPassword::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });

        $token = Password::broker()->createToken($this->user->fresh()); // the plain token behind the e-mailed link
        $this->postJson('/api/auth/reset-password', ['email' => $this->user->email, 'token' => $token, 'password' => 'BrandNew123!', 'password_confirmation' => 'BrandNew123!'])->assertOk();

        $this->postJson('/api/auth/login', ['identifier' => '01712345678', 'password' => 'BrandNew123!'])->assertOk();
        $this->postJson('/api/auth/login', ['identifier' => '01712345678', 'password' => 'OldSecret123!'])->assertStatus(422);
        // a link works once
        $this->postJson('/api/auth/reset-password', ['email' => $this->user->email, 'token' => $token, 'password' => 'Another123!x', 'password_confirmation' => 'Another123!x'])->assertStatus(422);
    }

    public function test_an_unknown_address_gets_the_same_answer_and_no_mail(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/forgot-password', ['identifier' => 'nobody@gmail.com'])->assertOk()->assertJsonPath('channel', 'email');
        Notification::assertNothingSent();
    }

    public function test_forgot_password_by_mobile_uses_an_sms_code(): void
    {
        $this->postJson('/api/auth/forgot-password', ['identifier' => '01712345678'])->assertOk()->assertJsonPath('channel', 'sms');

        // the code was generated for this number; read it back the way a user would by getting it by SMS
        $code = '123456';
        \App\Models\Otp::where('mobile', '01712345678')->update(['code_hash' => \Illuminate\Support\Facades\Hash::make($code)]);

        $this->postJson('/api/auth/reset-password', ['mobile' => '01712345678', 'code' => '000000', 'password' => 'ViaSms123!x', 'password_confirmation' => 'ViaSms123!x'])->assertStatus(422);
        $this->postJson('/api/auth/reset-password', ['mobile' => '+8801712345678', 'code' => $code, 'password' => 'ViaSms123!x', 'password_confirmation' => 'ViaSms123!x'])->assertOk();
        $this->postJson('/api/auth/login', ['identifier' => '01712345678', 'password' => 'ViaSms123!x'])->assertOk();
    }

    public function test_a_reset_logs_out_every_old_session_and_enforces_the_password_rules(): void
    {
        $this->user->createToken('old');
        $token = Password::broker()->createToken($this->user);

        $this->postJson('/api/auth/reset-password', ['email' => $this->user->email, 'token' => $token, 'password' => 'short', 'password_confirmation' => 'short'])->assertStatus(422)->assertJsonValidationErrors('password');
        $this->postJson('/api/auth/reset-password', ['email' => $this->user->email, 'token' => $token, 'password' => 'GoodPass123!', 'password_confirmation' => 'different1'])->assertStatus(422);
        $this->postJson('/api/auth/reset-password', ['email' => $this->user->email, 'token' => $token, 'password' => 'GoodPass123!', 'password_confirmation' => 'GoodPass123!'])->assertOk();

        $this->assertSame(0, $this->user->tokens()->count());
    }
}
