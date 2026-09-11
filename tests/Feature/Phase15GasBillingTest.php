<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\BillingPeriod;
use App\Models\Flat;
use App\Models\GasConsumer;
use App\Models\GasPurchase;
use App\Models\GasRateSetting;
use App\Models\User;
use App\Services\GasBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Phase15GasBillingTest extends TestCase
{
    use RefreshDatabase;

    private GasBillingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(GasBillingService::class);
        GasRateSetting::create(['effective_from' => '2026-01-01', 'method' => 'FIXED', 'rate' => 330, 'm3_per_kg' => 0.54]);
    }

    public function test_units_used_is_current_minus_previous(): void
    {
        $user = User::factory()->create();
        $flat = Flat::factory()->create();
        $consumer = GasConsumer::create(['flat_id' => $flat->id, 'name' => 'Test', 'is_internal' => false, 'active' => true]);
        $period = BillingPeriod::create(['year' => 2026, 'month' => 8, 'status' => 'DRAFT']);

        $this->service->recordReading($consumer, $period->id, 100, '2026-08-05', $user->id);
        $reading = $this->service->recordReading(
            GasConsumer::find($consumer->id),
            BillingPeriod::create(['year' => 2026, 'month' => 9, 'status' => 'DRAFT'])->id,
            150,
            '2026-09-05',
            $user->id,
        );

        $this->assertSame('50.0000', $reading->units_used);
        $this->assertSame('100.0000', $reading->previous_reading);
    }

    public function test_current_reading_below_previous_is_rejected(): void
    {
        $user = User::factory()->create();
        $flat = Flat::factory()->create();
        $consumer = GasConsumer::create(['flat_id' => $flat->id, 'name' => 'Test', 'is_internal' => false, 'active' => true]);
        $period1 = BillingPeriod::create(['year' => 2026, 'month' => 8, 'status' => 'DRAFT']);
        $period2 = BillingPeriod::create(['year' => 2026, 'month' => 9, 'status' => 'DRAFT']);

        $this->service->recordReading($consumer, $period1->id, 100, '2026-08-05', $user->id);

        $this->expectException(ValidationException::class);
        $this->service->recordReading($consumer->fresh(), $period2->id, 90, '2026-09-05', $user->id);
    }

    public function test_apply_to_bills_updates_gas_amount_and_total_payable(): void
    {
        $user = User::factory()->create();
        $flat = Flat::factory()->create(['occupancy_status' => 'OWNER_OCCUPIED', 'service_charge_rate' => 3500]);
        $consumer = GasConsumer::create(['flat_id' => $flat->id, 'name' => 'Test', 'is_internal' => false, 'active' => true]);
        $period = BillingPeriod::create(['year' => 2026, 'month' => 8, 'status' => 'DRAFT']);
        $bill = Bill::create([
            'billing_period_id' => $period->id, 'flat_id' => $flat->id,
            'service_charge' => 3500, 'gas_amount' => 0, 'previous_due' => 0,
            'total_payable' => 3500, 'due_date' => '2026-08-10', 'status' => 'UNPAID',
        ]);

        $this->service->recordReading($consumer, $period->id, 10, '2026-08-05', $user->id); // 10 m3 used

        $this->service->applyToBills($period->id);

        $bill->refresh();
        $this->assertSame('3300.0000', $bill->gas_amount); // 10 * 330
        $this->assertSame('6800.0000', $bill->total_payable); // 3500 + 3300
    }

    public function test_internal_consumer_is_excluded_from_bills(): void
    {
        $user = User::factory()->create();
        $office = GasConsumer::create(['name' => 'প্রজেক্ট অফিস', 'is_internal' => true, 'active' => true]);
        $period = BillingPeriod::create(['year' => 2026, 'month' => 8, 'status' => 'DRAFT']);

        $this->service->recordReading($office, $period->id, 20, '2026-08-05', $user->id);

        // Should not throw looking for a flat/bill, and should not create one.
        $this->service->applyToBills($period->id);
        $this->assertDatabaseCount('bills', 0);
    }

    public function test_system_loss_percent_formula(): void
    {
        $user = User::factory()->create();
        $flat = Flat::factory()->create();
        $consumer = GasConsumer::create(['flat_id' => $flat->id, 'name' => 'Test', 'is_internal' => false, 'active' => true]);
        $period = BillingPeriod::create(['year' => 2026, 'month' => 8, 'status' => 'DRAFT']);

        // 1000kg * 0.54 = 540 m3 purchased.
        GasPurchase::create([
            'date' => '2026-08-10', 'supplier_name' => 'Test Supplier',
            'quantity_kg' => 1000, 'unit_price' => 100, 'total_cost' => 100000,
        ]);

        // Consumer used 500 m3 total -> loss = (540-500)/540*100 = 7.41%
        $this->service->recordReading($consumer, $period->id, 500, '2026-08-20', $user->id);

        $loss = $this->service->systemLossPercent(2026, 8, $period->id);

        $this->assertSame(7.41, $loss);
    }
}
