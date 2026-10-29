<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\BillingPeriod;
use App\Models\Flat;
use App\Models\FlatParty;
use App\Models\Payment;
use App\Models\Person;
use App\Models\User;
use App\Services\PaymentService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A resident reports a bKash/Nagad/bank payment (stays PENDING until the office approves) and downloads the receipt. */
class ResidentPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Flat $flat;

    private Bill $bill;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RoleSeeder::class);
        $person = Person::factory()->create();
        $this->owner = User::factory()->create(['person_id' => $person->id, 'role' => 'flat_owner']);
        $this->flat = Flat::factory()->create();
        FlatParty::create(['flat_id' => $this->flat->id, 'person_id' => $person->id, 'role' => 'OWNER', 'from_date' => '2026-01-01']);
        $period = BillingPeriod::create(['year' => 2026, 'month' => 9, 'status' => 'GENERATED']);
        $this->bill = Bill::create([
            'billing_period_id' => $period->id, 'flat_id' => $this->flat->id, 'service_charge' => 3500, 'gas_amount' => 0, 'other_charges' => 0,
            'late_fee' => 0, 'previous_due' => 0, 'total_payable' => 3500, 'paid_amount' => 0, 'due_date' => '2026-09-10', 'status' => 'UNPAID',
        ]);
    }

    private function body(array $over = []): array
    {
        return $over + ['flat_id' => $this->flat->id, 'bill_id' => $this->bill->id, 'amount' => '3500', 'method' => 'BKASH', 'reference_no' => 'TRX123ABC', 'payment_date' => '2026-09-12'];
    }

    public function test_a_report_becomes_a_pending_payment_nothing_is_credited(): void
    {
        $this->actingAs($this->owner, 'sanctum')->postJson('/api/me/payments', $this->body())->assertCreated()->assertJsonPath('status', 'PENDING');

        $this->assertDatabaseHas('payments', ['reference_no' => 'TRX123ABC', 'status' => 'PENDING', 'received_by' => $this->owner->id]);
        $this->assertSame('0.0000', $this->bill->fresh()->paid_amount);
    }

    public function test_the_same_transaction_id_cannot_be_reported_twice(): void
    {
        $api = $this->actingAs($this->owner, 'sanctum');
        $api->postJson('/api/me/payments', $this->body())->assertCreated();
        $api->postJson('/api/me/payments', $this->body(['amount' => '100']))->assertStatus(422)->assertJsonValidationErrors('reference_no');
    }

    public function test_only_own_flat_a_real_method_and_a_sane_amount(): void
    {
        $api = $this->actingAs($this->owner, 'sanctum');
        $api->postJson('/api/me/payments', $this->body(['flat_id' => Flat::factory()->create()->id]))->assertStatus(422)->assertJsonValidationErrors('flat_id');
        $api->postJson('/api/me/payments', $this->body(['method' => 'CASH']))->assertStatus(422)->assertJsonValidationErrors('method');
        $api->postJson('/api/me/payments', $this->body(['amount' => '99999']))->assertStatus(422)->assertJsonValidationErrors('amount');
        $api->postJson('/api/me/payments', $this->body(['reference_no' => '']))->assertStatus(422)->assertJsonValidationErrors('reference_no');
        $api->postJson('/api/me/payments', $this->body(['payment_date' => '2999-01-01']))->assertStatus(422)->assertJsonValidationErrors('payment_date');
    }

    public function test_list_shows_only_own_payments_and_the_receipt_needs_approval_and_ownership(): void
    {
        $mine = Payment::create(['flat_id' => $this->flat->id, 'bill_id' => $this->bill->id, 'amount' => 1000, 'payment_date' => '2026-09-12', 'method' => 'BKASH', 'reference_no' => 'A1', 'received_by' => $this->owner->id, 'status' => 'PENDING']);
        $other = Payment::create(['flat_id' => Flat::factory()->create()->id, 'amount' => 500, 'payment_date' => '2026-09-12', 'method' => 'CASH', 'received_by' => $this->owner->id, 'status' => 'PENDING']);

        $api = $this->actingAs($this->owner, 'sanctum');
        $this->assertSame([$mine->id], collect($api->getJson('/api/me/payments')->json())->pluck('id')->all());

        $api->get("/api/me/payments/{$mine->id}/receipt", ['Accept' => 'application/json'])->assertStatus(422);   // not approved yet
        $api->get("/api/me/payments/{$other->id}/receipt", ['Accept' => 'application/json'])->assertNotFound();    // someone else's

        $admin = User::factory()->create(['role' => 'admin']);
        \App\Models\BankAccount::create(['bank_name' => 'Test', 'account_title' => 'GV', 'account_no' => '1', 'opening_balance' => 0, 'current_balance' => 0]);
        app(PaymentService::class)->approve($mine, $admin->id);
        $this->actingAs($this->owner, 'sanctum')->get("/api/me/payments/{$mine->id}/receipt")->assertOk();
    }
}
