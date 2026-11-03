<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\MoneyCorePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** What the payment pop-up collects: method-specific details, a note, and no double-entry of the same transaction number. */
class PaymentDetailsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Flat $flat;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([ChartOfAccountsSeeder::class, RoleSeeder::class, MoneyCorePermissionSeeder::class] as $s) {
            $this->seed($s);
        }
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->assignRole('admin');
        $this->flat = Flat::factory()->create();
    }

    private function pay(array $over = [])
    {
        return $this->actingAs($this->admin, 'sanctum')->postJson('/api/payments', $over + [
            'flat_id' => $this->flat->id, 'amount' => '1000', 'payment_date' => now()->toDateString(), 'method' => 'CASH',
        ]);
    }

    public function test_cash_needs_no_reference_and_keeps_the_note(): void
    {
        $this->pay(['remarks' => 'paid by the owner in person'])->assertCreated()->assertJsonPath('remarks', 'paid by the owner in person');
    }

    public function test_a_bank_payment_needs_the_bank_name_and_cheque_number(): void
    {
        $this->pay(['method' => 'BANK'])->assertStatus(422)->assertJsonValidationErrors(['reference_no', 'bank_name']);
        $this->pay(['method' => 'BANK', 'reference_no' => 'CHQ-1'])->assertStatus(422)->assertJsonValidationErrors('bank_name');
        $this->pay(['method' => 'BANK', 'reference_no' => 'CHQ-1', 'bank_name' => 'Dutch-Bangla Bank'])
            ->assertCreated()->assertJsonPath('bank_name', 'Dutch-Bangla Bank')->assertJsonPath('reference_no', 'CHQ-1');
    }

    public function test_mobile_banking_needs_a_transaction_id_and_accepts_every_provider(): void
    {
        $this->pay(['method' => 'BKASH'])->assertStatus(422)->assertJsonValidationErrors('reference_no');
        foreach (['BKASH', 'NAGAD', 'ROCKET', 'UPAY'] as $i => $provider) {
            $this->pay(['method' => $provider, 'reference_no' => "TX{$i}"])->assertCreated()->assertJsonPath('method', $provider);
        }
    }

    public function test_the_same_transaction_number_cannot_be_entered_twice(): void
    {
        $this->pay(['method' => 'BKASH', 'reference_no' => '9J58AXK2'])->assertCreated();
        $this->pay(['method' => 'BKASH', 'reference_no' => '9J58AXK2'])->assertStatus(422)->assertJsonValidationErrors('reference_no');
        // another provider's wallet may legitimately reuse a number
        $this->pay(['method' => 'NAGAD', 'reference_no' => '9J58AXK2'])->assertCreated();
    }

    public function test_a_future_payment_date_is_refused(): void
    {
        $this->pay(['payment_date' => now()->addDays(3)->toDateString()])->assertStatus(422)->assertJsonValidationErrors('payment_date');
    }

    public function test_the_register_filters_by_status_method_and_text_and_pages(): void
    {
        $this->pay(['method' => 'BKASH', 'reference_no' => 'TX-ALPHA'])->assertCreated();
        $this->pay(['method' => 'CASH'])->assertCreated();
        $api = $this->actingAs($this->admin, 'sanctum');

        $this->assertSame(2, $api->getJson('/api/payments')->json('meta.total'));
        $this->assertSame(1, $api->getJson('/api/payments?method=BKASH')->json('meta.total'));
        $this->assertSame(1, $api->getJson('/api/payments?q=ALPHA')->json('meta.total'));
        $this->assertSame(0, $api->getJson('/api/payments?status=APPROVED')->json('meta.total'));
        $this->assertSame(2, $api->getJson('/api/payments?status=PENDING&per_page=1')->json('meta.last_page'));
    }
}
