<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\BillingPeriod;
use App\Models\Flat;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase16PaymentsTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PaymentService::class);
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);
    }

    private function makeBill(): Bill
    {
        $flat = Flat::factory()->create(['occupancy_status' => 'OWNER_OCCUPIED', 'service_charge_rate' => 3500]);
        $period = BillingPeriod::create(['year' => 2026, 'month' => 8, 'status' => 'GENERATED']);

        return Bill::create([
            'billing_period_id' => $period->id, 'flat_id' => $flat->id,
            'service_charge' => 3500, 'gas_amount' => 0, 'previous_due' => 0,
            'total_payable' => 3500, 'paid_amount' => 0, 'due_date' => '2026-08-10', 'status' => 'UNPAID',
        ]);
    }

    public function test_payment_starts_pending_and_does_not_touch_the_bill(): void
    {
        $manager = User::factory()->create();
        $bill = $this->makeBill();

        $payment = $this->service->create([
            'flat_id' => $bill->flat_id, 'bill_id' => $bill->id, 'amount' => 3500,
            'payment_date' => '2026-08-05', 'method' => 'CASH',
        ], $manager->id);

        $this->assertSame('PENDING', $payment->status);
        $this->assertNull($payment->receipt_no);
        $this->assertSame('0.0000', $bill->fresh()->paid_amount);
        $this->assertDatabaseCount('cash_ledger', 0);
    }

    public function test_approving_generates_receipt_and_updates_bill_to_paid(): void
    {
        $manager = User::factory()->create();
        $admin = User::factory()->create();
        $bill = $this->makeBill();

        $payment = $this->service->create([
            'flat_id' => $bill->flat_id, 'bill_id' => $bill->id, 'amount' => 3500,
            'payment_date' => '2026-08-05', 'method' => 'CASH',
        ], $manager->id);

        $approved = $this->service->approve($payment, $admin->id);

        $this->assertSame('APPROVED', $approved->status);
        $this->assertSame('MR-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT), $approved->receipt_no);
        $this->assertSame('PAID', $bill->fresh()->status);
        $this->assertSame('3500.0000', $bill->fresh()->paid_amount);
        $this->assertDatabaseHas('cash_ledger', ['in_amount' => 3500]);
    }

    public function test_partial_payment_leaves_bill_partial(): void
    {
        $manager = User::factory()->create();
        $admin = User::factory()->create();
        $bill = $this->makeBill();

        $payment = $this->service->create([
            'flat_id' => $bill->flat_id, 'bill_id' => $bill->id, 'amount' => 2000,
            'payment_date' => '2026-08-05', 'method' => 'CASH',
        ], $manager->id);
        $this->service->approve($payment, $admin->id);

        $this->assertSame('PARTIAL', $bill->fresh()->status);
        $this->assertSame('2000.0000', $bill->fresh()->paid_amount);
    }

    public function test_approved_journal_entry_is_balanced(): void
    {
        $manager = User::factory()->create();
        $admin = User::factory()->create();
        $bill = $this->makeBill();

        $payment = $this->service->create([
            'flat_id' => $bill->flat_id, 'bill_id' => $bill->id, 'amount' => 3500,
            'payment_date' => '2026-08-05', 'method' => 'CASH',
        ], $manager->id);
        $this->service->approve($payment, $admin->id);

        $entry = \App\Models\JournalEntry::latest('id')->first();
        $totalDebit = $entry->lines()->sum('debit');
        $totalCredit = $entry->lines()->sum('credit');
        $this->assertEquals((float) $totalDebit, (float) $totalCredit);
        $this->assertEquals(3500, (float) $totalDebit);
    }

    public function test_receipt_numbers_never_collide_under_concurrent_approval(): void
    {
        $manager = User::factory()->create();
        $admin = User::factory()->create();
        $flat = Flat::factory()->create();

        $payments = [];
        for ($i = 0; $i < 10; $i++) {
            $payments[] = $this->service->create([
                'flat_id' => $flat->id, 'amount' => 100 + $i,
                'payment_date' => '2026-08-05', 'method' => 'CASH',
            ], $manager->id);
        }

        $receiptNumbers = collect($payments)
            ->map(fn (Payment $p) => $this->service->approve($p, $admin->id)->receipt_no)
            ->all();

        $this->assertCount(10, array_unique($receiptNumbers));
    }

    public function test_cannot_approve_an_already_approved_payment(): void
    {
        $manager = User::factory()->create();
        $admin = User::factory()->create();
        $bill = $this->makeBill();

        $payment = $this->service->create([
            'flat_id' => $bill->flat_id, 'bill_id' => $bill->id, 'amount' => 3500,
            'payment_date' => '2026-08-05', 'method' => 'CASH',
        ], $manager->id);
        $this->service->approve($payment, $admin->id);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->service->approve($payment->fresh(), $admin->id);
    }
}
