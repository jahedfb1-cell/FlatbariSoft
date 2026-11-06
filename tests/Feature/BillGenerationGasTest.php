<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\BillingPeriod;
use App\Models\Flat;
use App\Models\FlatParty;
use App\Models\GasConsumer;
use App\Models\GasMeterReading;
use App\Models\GasRateSetting;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Bills are not made before the month's gas readings are in - unless the office says so - and readings already in go straight into the bills. */
class BillGenerationGasTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([\Database\Seeders\RoleSeeder::class, \Database\Seeders\MoneyCorePermissionSeeder::class, \Database\Seeders\CollectionHeadSeeder::class]);
        $this->admin = User::factory()->create(['role' => 'super_admin']);
        $this->admin->assignRole('super_admin');
        GasRateSetting::create(['effective_from' => '2026-01-01', 'method' => 'FIXED', 'rate' => 325, 'm3_per_kg' => 0.54, 'minimum_charge' => 0]);
    }

    private function read(Flat $flat, BillingPeriod $period, float $units): void
    {
        $consumer = GasConsumer::firstOrCreate(['flat_id' => $flat->id], ['name' => 'Flat '.$flat->flat_no, 'is_internal' => false, 'active' => true]);
        GasMeterReading::create([
            'gas_consumer_id' => $consumer->id, 'billing_period_id' => $period->id, 'previous_reading' => 0,
            'current_reading' => $units, 'units_used' => $units, 'reading_date' => '2026-09-30', 'entered_by' => $this->admin->id,
        ]);
    }

    private function generate(array $extra = [])
    {
        return $this->actingAs($this->admin, 'sanctum')->postJson('/api/billing/generate', ['year' => 2026, 'month' => 9] + $extra);
    }

    public function test_bills_wait_for_the_gas_readings_and_say_how_many_are_missing(): void
    {
        $a = Flat::factory()->create(['occupancy_status' => 'OWNER_OCCUPIED']);
        Flat::factory()->create(['occupancy_status' => 'OWNER_OCCUPIED']);
        $period = BillingPeriod::create(['year' => 2026, 'month' => 9, 'status' => 'DRAFT']);
        $this->read($a, $period, 7);

        $this->generate()->assertStatus(422)->assertJsonValidationErrors('gas');

        $this->assertSame(0, Bill::count());
        $this->assertSame('DRAFT', $period->fresh()->status);
    }

    public function test_the_office_can_still_make_bills_without_gas_when_it_says_so(): void
    {
        $flat = Flat::factory()->create(['occupancy_status' => 'OWNER_OCCUPIED', 'service_charge_rate' => 3500]);

        $this->generate(['allow_without_gas' => true])->assertCreated();

        $bill = Bill::where('flat_id', $flat->id)->firstOrFail();
        $this->assertSame('0.0000', (string) $bill->gas_amount);
        $this->assertSame('3500.0000', (string) $bill->total_payable);
    }

    public function test_readings_already_in_go_into_the_bills_and_into_the_notice(): void
    {
        $a = Flat::factory()->create(['occupancy_status' => 'OWNER_OCCUPIED', 'service_charge_rate' => 3500]);
        $b = Flat::factory()->create(['occupancy_status' => 'OWNER_OCCUPIED', 'service_charge_rate' => 3500]);
        $person = Person::factory()->create();
        FlatParty::create(['flat_id' => $a->id, 'person_id' => $person->id, 'role' => 'OWNER', 'from_date' => '2026-01-01']);
        $owner = User::factory()->create(['person_id' => $person->id, 'role' => 'flat_owner']);
        $period = BillingPeriod::create(['year' => 2026, 'month' => 9, 'status' => 'DRAFT']);
        $this->read($a, $period, 7);
        $this->read($b, $period, 0);

        $this->generate()->assertCreated();

        $billA = Bill::where('flat_id', $a->id)->firstOrFail();
        $this->assertSame('2275.0000', (string) $billA->gas_amount);
        $this->assertSame('5775.0000', (string) $billA->total_payable); // 3,500 + 7 x 325
        $this->assertSame('3500.0000', (string) Bill::where('flat_id', $b->id)->value('total_payable'));
        $this->assertStringContainsString('5,775.00', $owner->notifications->first()->data['body']);
    }
}
