<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\GasConsumer;
use App\Models\User;
use Database\Seeders\GasSetupSeeder;
use Database\Seeders\MoneyCorePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The gas page must list every flat, not just the ones someone registered a meter for. */
class GasConsumerListTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_flat_appears_once_and_the_office_comes_first(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(MoneyCorePermissionSeeder::class);
        $this->seed(GasSetupSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        Flat::factory()->count(3)->create();

        $first = $this->actingAs($admin, 'sanctum')->getJson('/api/gas/consumers')->assertOk();
        $this->assertCount(4, $first->json());
        $this->assertTrue($first->json('0.is_internal'));
        $this->assertSame(3, collect($first->json())->whereNotNull('flat_id')->count());

        // a second call (and a flat added later) never duplicates
        Flat::factory()->create();
        $second = $this->actingAs($admin, 'sanctum')->getJson('/api/gas/consumers')->assertOk();
        $this->assertCount(5, $second->json());
        $this->assertSame(5, GasConsumer::count());
    }

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $this->seed(MoneyCorePermissionSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_a_consumer_can_be_added_edited_and_switched_off_by_hand(): void
    {
        $admin = $this->admin();
        $flat = Flat::factory()->create();

        $made = $this->actingAs($admin, 'sanctum')->postJson('/api/gas/consumers', [
            'flat_id' => $flat->id, 'name' => 'Flat X second meter', 'meter_no' => 'M-77',
        ])->assertCreated();
        $id = $made->json('id');
        $this->assertFalse($made->json('is_internal'));

        $other = $this->actingAs($admin, 'sanctum')->postJson('/api/gas/consumers', ['name' => 'Guard room', 'is_internal' => true])->assertCreated();
        $this->assertTrue($other->json('is_internal'));
        $this->assertNull($other->json('flat_id'));

        $this->actingAs($admin, 'sanctum')->putJson("/api/gas/consumers/{$id}", ['meter_no' => 'M-78', 'active' => false])->assertOk();
        $this->assertDatabaseHas('gas_consumers', ['id' => $id, 'meter_no' => 'M-78', 'active' => false]);

        // switched-off consumers drop out of the list unless asked for
        $ids = fn ($url) => collect($this->actingAs($admin, 'sanctum')->getJson($url)->json())->pluck('id');
        $this->assertFalse($ids('/api/gas/consumers')->contains($id));
        $this->assertTrue($ids('/api/gas/consumers?all=1')->contains($id));

        // a consumer cannot be moved to another flat, and a name is required
        $this->actingAs($admin, 'sanctum')->putJson("/api/gas/consumers/{$id}", ['flat_id' => $flat->id])->assertStatus(422);
        $this->actingAs($admin, 'sanctum')->postJson('/api/gas/consumers', ['meter_no' => 'x'])->assertStatus(422);
    }

    public function test_viewers_cannot_add_consumers(): void
    {
        $this->admin();
        $viewer = User::factory()->create(['role' => 'tenant']);
        $this->actingAs($viewer, 'sanctum')->postJson('/api/gas/consumers', ['name' => 'x'])->assertForbidden();
    }

    public function test_summary_totals_purchases_and_what_flats_used_this_month(): void
    {
        $admin = $this->admin();
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);
        $this->seed(\Database\Seeders\ExpenseHeadSeeder::class);
        $this->seed(GasSetupSeeder::class);
        \App\Models\GasRateSetting::query()->update(['m3_per_kg' => 0.5]);
        $flatA = Flat::factory()->create();
        $flatB = Flat::factory()->create();
        $this->actingAs($admin, 'sanctum')->getJson('/api/gas/consumers')->assertOk(); // creates the consumers

        app(\App\Services\GasPurchaseService::class)->record(['date' => '2026-09-05', 'supplier_name' => 'S', 'quantity_kg' => '400', 'unit_price' => '95', 'transport_cost' => '0', 'treasury_medium' => 'CASH'], $admin->id);
        app(\App\Services\GasPurchaseService::class)->record(['date' => '2026-08-05', 'supplier_name' => 'S', 'quantity_kg' => '100', 'unit_price' => '100', 'transport_cost' => '0', 'treasury_medium' => 'CASH'], $admin->id);

        $period = \App\Models\BillingPeriod::create(['year' => 2026, 'month' => 9, 'status' => 'DRAFT']);
        $svc = app(\App\Services\GasBillingService::class);
        $svc->recordReading(GasConsumer::where('flat_id', $flatA->id)->first(), $period->id, 30, '2026-09-28', $admin->id);
        $svc->recordReading(GasConsumer::where('flat_id', $flatB->id)->first(), $period->id, 20, '2026-09-28', $admin->id);
        $svc->recordReading(GasConsumer::where('is_internal', true)->first(), $period->id, 10, '2026-09-28', $admin->id);

        $res = $this->actingAs($admin, 'sanctum')->getJson('/api/gas/summary?year=2026&month=9')->assertOk();
        $this->assertSame(2, $res->json('total_purchases'));
        $this->assertSame('500.0000', $res->json('total_kg'));
        $this->assertSame('48000.0000', $res->json('total_cost'));
        $this->assertSame('400.0000', $res->json('month_kg'));
        $this->assertSame('200.0000', $res->json('month_bought_m3'));
        $this->assertSame('50.0000', $res->json('flats_used_m3'));
        $this->assertSame('10.0000', $res->json('office_used_m3'));
        $this->assertSame(2, $res->json('flats_read'));
        $this->assertSame(2, $res->json('flats_total'));
        $this->assertSame('140.0000', $res->json('loss_m3'));
        $this->assertEquals(70.0, $res->json('loss_percent'));
    }
}
