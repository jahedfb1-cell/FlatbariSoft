<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A bank account carries its branch, up to three holders with phones, and the number the bank texts. */
class BankAccountDetailsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(\Database\Seeders\MoneyCorePermissionSeeder::class);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->assignRole('admin');
    }

    private function body(array $over = []): array
    {
        return $over + [
            'bank_name' => 'Islami Bank', 'branch' => 'Mirpur', 'account_no' => '2050123', 'account_title' => 'Green Valley Owners', 'opening_balance' => 5000,
            'holder_1_name' => 'Rahim', 'holder_1_phone' => '01711111111', 'holder_2_name' => 'Karim', 'holder_2_phone' => '01822222222',
            'holder_3_name' => 'Salma', 'holder_3_phone' => '+8801933333333', 'sms_phone' => '01711111111',
        ];
    }

    public function test_an_account_is_created_with_branch_holders_and_sms_phone(): void
    {
        $res = $this->actingAs($this->admin, 'sanctum')->postJson('/api/bank-accounts', $this->body())->assertCreated();

        $res->assertJsonPath('branch', 'Mirpur')->assertJsonPath('sms_phone', '01711111111')
            ->assertJsonPath('holders.0.name', 'Rahim')->assertJsonPath('holders.1.phone', '01822222222')->assertJsonPath('holders.2.name', 'Salma');
        $this->assertCount(3, $res->json('holders'));
        $this->assertSame('5000.0000', $res->json('current_balance'));
    }

    public function test_holders_are_optional(): void
    {
        $res = $this->actingAs($this->admin, 'sanctum')->postJson('/api/bank-accounts', [
            'bank_name' => 'B', 'account_no' => '1', 'account_title' => 'T', 'holder_1_name' => 'Only One',
        ])->assertCreated();
        $this->assertNull($res->json('holders.1.name'));
    }

    public function test_details_can_be_edited_but_never_the_balance(): void
    {
        $acc = BankAccount::create(['bank_name' => 'B', 'account_no' => '1', 'account_title' => 'T', 'opening_balance' => 100, 'current_balance' => 100]);

        $this->actingAs($this->admin, 'sanctum')->putJson("/api/bank-accounts/{$acc->id}", [
            'branch' => 'Gulshan', 'holder_2_name' => 'New Holder', 'holder_2_phone' => '01555555555', 'sms_phone' => '01666666666', 'opening_balance' => 999999, 'current_balance' => 888,
        ])->assertOk()->assertJsonPath('branch', 'Gulshan')->assertJsonPath('holders.1.name', 'New Holder')->assertJsonPath('sms_phone', '01666666666');

        $fresh = $acc->fresh();
        $this->assertSame('100.0000', $fresh->opening_balance);
        $this->assertSame('100.0000', $fresh->current_balance);
        $this->assertSame('B', $fresh->bank_name);   // untouched fields stay
    }

    public function test_bad_phone_numbers_are_rejected(): void
    {
        $api = $this->actingAs($this->admin, 'sanctum');
        $api->postJson('/api/bank-accounts', $this->body(['holder_1_phone' => 'call me']))->assertStatus(422)->assertJsonValidationErrors('holder_1_phone');
        $api->postJson('/api/bank-accounts', $this->body(['sms_phone' => '12']))->assertStatus(422)->assertJsonValidationErrors('sms_phone');
    }

    public function test_only_people_who_manage_accounts_can_edit(): void
    {
        $acc = BankAccount::create(['bank_name' => 'B', 'account_no' => '1', 'account_title' => 'T', 'opening_balance' => 0, 'current_balance' => 0]);
        $tenant = User::factory()->create(['role' => 'tenant']);
        $this->actingAs($tenant, 'sanctum')->putJson("/api/bank-accounts/{$acc->id}", ['branch' => 'x'])->assertForbidden();
    }
}
