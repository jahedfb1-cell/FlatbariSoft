<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Flat;
use App\Models\User;
use App\Services\BillGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Phase14BillingTest extends TestCase
{
    use RefreshDatabase;

    private BillGenerationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BillGenerationService::class);
        $this->seed(\Database\Seeders\CollectionHeadSeeder::class);
    }

    public function test_generates_bills_for_every_active_flat_with_correct_rate(): void
    {
        $user = User::factory()->create();
        $occupied = Flat::factory()->create(['occupancy_status' => 'OWNER_OCCUPIED', 'service_charge_rate' => 3500, 'empty_flat_rate' => 2500]);
        $empty = Flat::factory()->create(['occupancy_status' => 'EMPTY', 'service_charge_rate' => 3500, 'empty_flat_rate' => 2500]);

        $this->service->generate(2026, 8, $user->id);

        $this->assertDatabaseHas('bills', ['flat_id' => $occupied->id, 'service_charge' => 3500, 'previous_due' => 0, 'total_payable' => 3500]);
        $this->assertDatabaseHas('bills', ['flat_id' => $empty->id, 'service_charge' => 2500, 'total_payable' => 2500]);
    }

    public function test_due_date_is_the_tenth_of_the_month(): void
    {
        $user = User::factory()->create();
        Flat::factory()->create();

        $this->service->generate(2026, 8, $user->id);

        $bill = Bill::first();
        $this->assertSame('2026-08-10', $bill->due_date->toDateString());
    }

    public function test_previous_due_carries_forward_unpaid_balance(): void
    {
        $user = User::factory()->create();
        $flat = Flat::factory()->create(['occupancy_status' => 'OWNER_OCCUPIED', 'service_charge_rate' => 3500]);

        $this->service->generate(2026, 7, $user->id);
        $julyBill = Bill::first();
        // Nothing paid — full amount should carry forward.
        $julyBill->update(['paid_amount' => 0]);

        $this->service->generate(2026, 8, $user->id);
        $augustBill = Bill::where('flat_id', $flat->id)
            ->whereHas('billingPeriod', fn ($q) => $q->where('month', 8))
            ->first();

        $this->assertSame('3500.0000', $augustBill->previous_due);
        $this->assertSame('7000.0000', $augustBill->total_payable); // 3500 new + 3500 due
    }

    public function test_previous_due_is_reduced_by_partial_payment(): void
    {
        $user = User::factory()->create();
        Flat::factory()->create(['occupancy_status' => 'OWNER_OCCUPIED', 'service_charge_rate' => 3500]);

        $this->service->generate(2026, 7, $user->id);
        $julyBill = Bill::first();
        $julyBill->update(['paid_amount' => 2000]); // paid 2000 of 3500

        $this->service->generate(2026, 8, $user->id);
        $augustBill = Bill::whereHas('billingPeriod', fn ($q) => $q->where('month', 8))->first();

        $this->assertSame('1500.0000', $augustBill->previous_due);
    }

    public function test_generating_the_same_period_twice_is_rejected(): void
    {
        $user = User::factory()->create();
        Flat::factory()->create();

        $this->service->generate(2026, 8, $user->id);

        $this->expectException(ValidationException::class);
        $this->service->generate(2026, 8, $user->id);
    }

    public function test_generation_creates_a_service_charge_bill_item(): void
    {
        $user = User::factory()->create();
        Flat::factory()->create(['occupancy_status' => 'OWNER_OCCUPIED', 'service_charge_rate' => 3500]);

        $this->service->generate(2026, 8, $user->id);

        $bill = Bill::first();
        $this->assertSame(1, $bill->items()->count());
        $this->assertDatabaseHas('bill_items', ['bill_id' => $bill->id, 'amount' => 3500]);
    }
}
