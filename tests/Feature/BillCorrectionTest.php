<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\BillingPeriod;
use App\Models\Flat;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\MoneyCorePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A wrong bill can be corrected by hand - with a reason, a server-side total, and without breaking what was paid or carried forward. */
class BillCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Bill $bill;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([ChartOfAccountsSeeder::class, RoleSeeder::class, MoneyCorePermissionSeeder::class] as $s) {
            $this->seed($s);
        }
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->assignRole('admin');
        $period = BillingPeriod::create(['year' => 2026, 'month' => 9, 'status' => 'GENERATED']);
        $this->bill = Bill::create([
            'billing_period_id' => $period->id, 'flat_id' => Flat::factory()->create()->id, 'service_charge' => 2000, 'gas_amount' => 0,
            'other_charges' => 0, 'late_fee' => 0, 'previous_due' => 500, 'total_payable' => 2500, 'paid_amount' => 0,
            'due_date' => '2026-09-10', 'status' => 'UNPAID',
        ]);
    }

    private function body(array $over = []): array
    {
        return $over + ['service_charge' => '2000', 'gas_amount' => '350', 'other_charges' => '0', 'late_fee' => '0', 'reason' => 'gas reading missed'];
    }

    public function test_the_server_recomputes_the_total(): void
    {
        $this->actingAs($this->admin, 'sanctum')->putJson("/api/billing/bills/{$this->bill->id}", $this->body())
            ->assertOk()->assertJsonPath('status', 'UNPAID');

        $this->assertSame('2850.0000', $this->bill->fresh()->total_payable); // 500 + 2000 + 350
    }

    public function test_a_reason_is_required_and_the_total_cannot_fall_below_what_was_paid(): void
    {
        $api = $this->actingAs($this->admin, 'sanctum');
        $api->putJson("/api/billing/bills/{$this->bill->id}", $this->body(['reason' => '']))->assertStatus(422)->assertJsonValidationErrors('reason');

        $this->bill->update(['paid_amount' => 2400, 'status' => 'PARTIAL']);
        $api->putJson("/api/billing/bills/{$this->bill->id}", $this->body(['service_charge' => '1000', 'gas_amount' => '0']))->assertStatus(422);

        // lowering it exactly to the paid amount settles the bill
        $api->putJson("/api/billing/bills/{$this->bill->id}", $this->body(['service_charge' => '1900', 'gas_amount' => '0']))->assertOk()->assertJsonPath('status', 'PAID');
    }

    public function test_a_bill_cannot_change_once_the_next_month_exists(): void
    {
        $next = BillingPeriod::create(['year' => 2026, 'month' => 10, 'status' => 'GENERATED']);
        Bill::create([
            'billing_period_id' => $next->id, 'flat_id' => $this->bill->flat_id, 'service_charge' => 2000, 'gas_amount' => 0, 'other_charges' => 0,
            'late_fee' => 0, 'previous_due' => 2500, 'total_payable' => 4500, 'paid_amount' => 0, 'due_date' => '2026-10-10', 'status' => 'UNPAID',
        ]);

        $this->actingAs($this->admin, 'sanctum')->putJson("/api/billing/bills/{$this->bill->id}", $this->body())->assertStatus(422)->assertJsonValidationErrors('bill');
    }

    public function test_only_people_who_manage_billing_can_correct(): void
    {
        $viewer = User::factory()->create(['role' => 'tenant']);
        $viewer->assignRole('tenant');

        $this->actingAs($viewer, 'sanctum')->putJson("/api/billing/bills/{$this->bill->id}", $this->body())->assertForbidden();
    }
}
