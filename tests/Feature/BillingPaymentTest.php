<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\BillingPeriod;
use App\Models\CashLedger;
use App\Models\Flat;
use App\Models\FlatParty;
use App\Models\Payment;
use App\Models\Person;
use App\Models\User;
use App\Services\PaymentService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\CommunityHallSeeder;
use Database\Seeders\MoneyCorePermissionSeeder;
use Database\Seeders\RoleCatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Taking a monthly bill's payment (service charge, gas and previous due together) from the billing page. */
class BillingPaymentTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([ChartOfAccountsSeeder::class, RoleSeeder::class, MoneyCorePermissionSeeder::class, CommunityHallSeeder::class, RoleCatalogSeeder::class] as $seeder) {
            $this->seed($seeder);
        }
        $this->service = app(PaymentService::class);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        $user->assignRole($role);

        return $user;
    }

    /** A September bill: service charge 3,000 + gas 1,000 + previous due 500 = 4,500. */
    private function bill(?Flat $flat = null): Bill
    {
        $flat ??= Flat::factory()->create(['flat_no' => '10A']);
        $period = BillingPeriod::firstOrCreate(['year' => 2026, 'month' => 9], ['status' => 'GENERATED']);

        return Bill::create([
            'billing_period_id' => $period->id, 'flat_id' => $flat->id, 'service_charge' => 3000, 'gas_amount' => 1000,
            'previous_due' => 500, 'total_payable' => 4500, 'paid_amount' => 0, 'due_date' => '2026-09-10', 'status' => 'UNPAID',
        ]);
    }

    private function pay(Bill $bill, string $amount, string $method = 'CASH', ?User $by = null): Payment
    {
        return $this->service->create([
            'flat_id' => $bill->flat_id, 'bill_id' => $bill->id, 'amount' => $amount, 'payment_date' => '2026-09-12', 'method' => $method,
        ], ($by ?? $this->user('manager'))->id);
    }

    // ---- guards

    public function test_a_payment_cannot_exceed_what_the_bill_still_owes(): void
    {
        $bill = $this->bill();

        $this->pay($bill, '4500'); // exactly the amount due is fine

        $this->expectException(ValidationException::class);
        $this->pay($bill, '0.01'); // the full amount is already waiting for approval
    }

    public function test_money_waiting_for_approval_counts_so_it_is_not_collected_twice(): void
    {
        $bill = $this->bill();
        $this->pay($bill, '3000');

        $this->assertSame('1500.0000', $this->service->collectable($bill->fresh()));
        try {
            $this->pay($bill, '1500.01');
            $this->fail('must be refused');
        } catch (ValidationException) {
        }
        $this->pay($bill, '1500');
        $this->assertSame('0.0000', $this->service->collectable($bill->fresh()));
    }

    public function test_the_bill_must_belong_to_the_flat(): void
    {
        $bill = $this->bill();
        $other = Flat::factory()->create();

        $this->expectException(ValidationException::class);
        $this->service->create(['flat_id' => $other->id, 'bill_id' => $bill->id, 'amount' => 100, 'payment_date' => '2026-09-12', 'method' => 'CASH'], $this->user('manager')->id);
    }

    public function test_a_payment_without_a_bill_is_still_allowed(): void
    {
        $flat = Flat::factory()->create();

        $p = $this->service->create(['flat_id' => $flat->id, 'amount' => 250, 'payment_date' => '2026-09-12', 'method' => 'CASH'], $this->user('manager')->id);

        $this->assertSame('PENDING', $p->status);
    }

    // ---- one payment, service charge and gas together

    public function test_one_payment_settles_service_charge_gas_and_previous_due_together(): void
    {
        $bill = $this->bill();
        $p = $this->pay($bill, '4500');
        $this->service->approve($p, $this->user('admin')->id);

        $this->assertSame('PAID', $bill->fresh()->status);
        $this->assertSame('4500.0000', $bill->fresh()->paid_amount);
        $this->assertSame('MR-'.str_pad((string) $p->id, 6, '0', STR_PAD_LEFT), $p->fresh()->receipt_no);
        $alloc = $this->service->allocation($p->fresh());
        $this->assertSame('3500.0000', $alloc['service']); // 3,000 charge + 500 previous due
        $this->assertSame('1000.0000', $alloc['gas']);
    }

    public function test_a_part_payment_is_divided_in_proportion_and_leaves_the_bill_partial(): void
    {
        $bill = $this->bill();
        $p = $this->pay($bill, '2250'); // half of 4,500
        $this->service->approve($p, $this->user('admin')->id);

        $alloc = $this->service->allocation($p->fresh());
        $this->assertSame('PARTIAL', $bill->fresh()->status);
        $this->assertSame('1750.0000', $alloc['service']);
        $this->assertSame('500.0000', $alloc['gas']);
        // the cash lands in each fund the same way
        $this->assertEquals(['GAS' => 500.0, 'OPERATING' => 1750.0], CashLedger::pluck('in_amount', 'fund_type')->map(fn ($v) => (float) $v)->all());
    }

    // ---- the register and the receipt over HTTP

    public function test_the_register_shows_due_pending_and_the_owner(): void
    {
        $flat = Flat::factory()->create(['flat_no' => '10A']);
        $person = Person::factory()->create(['name_en' => 'NAZRUL ISLAM']);
        FlatParty::create(['flat_id' => $flat->id, 'person_id' => $person->id, 'role' => 'OWNER', 'from_date' => '2022-01-01']);
        $bill = $this->bill($flat);
        $this->pay($bill, '1000');

        $row = $this->actingAs($this->user('admin'), 'sanctum')->getJson('/api/billing/2026/9')->assertOk()->json('0');

        $this->assertSame('NAZRUL ISLAM', $row['owner']);
        $this->assertEquals(4500, $row['due']);
        $this->assertSame('1000.0000', $row['pending_amount']);
        $this->assertSame('1000.0000', $row['gas_amount']);
    }

    public function test_the_whole_flow_over_http_with_a_receipt_at_the_end(): void
    {
        $bill = $this->bill();
        $manager = $this->user('manager');
        $admin = $this->user('admin');

        $created = $this->actingAs($manager, 'sanctum')->postJson('/api/payments', [
            'flat_id' => $bill->flat_id, 'bill_id' => $bill->id, 'amount' => 4500, 'payment_date' => '2026-09-12', 'method' => 'CASH',
        ])->assertCreated();
        $id = $created->json('id');

        $this->actingAs($manager, 'sanctum')->withHeader('Accept', 'application/json')->get("/api/payments/{$id}/receipt")->assertStatus(422); // not approved yet
        $this->actingAs($manager, 'sanctum')->postJson("/api/payments/{$id}/approve")->assertForbidden(); // maker is not the checker

        $this->actingAs($admin, 'sanctum')->postJson("/api/payments/{$id}/approve")->assertOk()->assertJsonPath('status', 'APPROVED');
        $pdf = $this->actingAs($manager, 'sanctum')->get("/api/payments/{$id}/receipt")->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
    }

    public function test_http_refuses_an_overpayment_and_a_flat_that_is_not_the_bills(): void
    {
        $bill = $this->bill();
        $other = Flat::factory()->create();
        $this->actingAs($this->user('manager'), 'sanctum');

        $this->postJson('/api/payments', ['flat_id' => $bill->flat_id, 'bill_id' => $bill->id, 'amount' => 4500.01, 'payment_date' => '2026-09-12', 'method' => 'CASH'])
            ->assertStatus(422)->assertJsonValidationErrors('amount');
        $this->postJson('/api/payments', ['flat_id' => $other->id, 'bill_id' => $bill->id, 'amount' => 100, 'payment_date' => '2026-09-12', 'method' => 'CASH'])
            ->assertStatus(422)->assertJsonValidationErrors('bill_id');
    }

    public function test_residents_cannot_take_payments_or_print_receipts(): void
    {
        $bill = $this->bill();
        $p = $this->pay($bill, '100');
        $owner = $this->user('flat_owner');

        $this->actingAs($owner, 'sanctum')->postJson('/api/payments', ['flat_id' => $bill->flat_id, 'amount' => 1, 'payment_date' => '2026-09-12', 'method' => 'CASH'])->assertForbidden();
        $this->actingAs($owner, 'sanctum')->get("/api/payments/{$p->id}/receipt")->assertForbidden();
    }

    public function test_a_flat_with_a_tenant_shows_both_names_and_a_flat_without_one_shows_only_the_owner(): void
    {
        $withTenant = Flat::factory()->create(['flat_no' => '10A']);
        $plain = Flat::factory()->create(['flat_no' => '9A']);
        foreach ([[$withTenant, 'NAZRUL ISLAM'], [$plain, 'PARVEZ AHMED']] as [$flat, $owner]) {
            $p = Person::factory()->create(['name_en' => $owner]);
            FlatParty::create(['flat_id' => $flat->id, 'person_id' => $p->id, 'role' => 'OWNER', 'from_date' => '2022-01-01']);
        }
        $tp = Person::factory()->create(['name_en' => 'RENTER ALI']);
        \App\Models\Tenant::factory()->create(['flat_id' => $withTenant->id, 'person_id' => $tp->id, 'status' => 'ACTIVE']);
        $gone = Person::factory()->create(['name_en' => 'OLD TENANT']);
        \App\Models\Tenant::factory()->create(['flat_id' => $plain->id, 'person_id' => $gone->id, 'status' => 'VACATED']);
        $this->bill($withTenant);
        $this->bill($plain);

        $rows = collect($this->actingAs($this->user('admin'), 'sanctum')->getJson('/api/billing/2026/9')->assertOk()->json())->keyBy('flat_no');

        $this->assertSame('NAZRUL ISLAM', $rows['10A']['owner']);
        $this->assertSame('RENTER ALI', $rows['10A']['tenant']);
        $this->assertSame('PARVEZ AHMED', $rows['9A']['owner']);
        $this->assertNull($rows['9A']['tenant']); // a tenant who has moved out is not shown
    }
}
