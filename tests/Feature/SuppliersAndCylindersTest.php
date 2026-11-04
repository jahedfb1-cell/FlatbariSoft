<?php

namespace Tests\Feature;

use App\Models\GasPurchase;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\ExpenseHeadSeeder;
use Database\Seeders\MoneyCorePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Suppliers are created once and picked on every purchase; gas is entered by the cylinder. */
class SuppliersAndCylindersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([ChartOfAccountsSeeder::class, ExpenseHeadSeeder::class, RoleSeeder::class, MoneyCorePermissionSeeder::class, \Database\Seeders\GasPermissionSeeder::class] as $s) {
            if (class_exists($s)) {
                $this->seed($s);
            }
        }
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->assignRole('admin');
    }

    private function supplier(array $over = []): int
    {
        return $this->actingAs($this->admin, 'sanctum')->postJson('/api/supplier-master', $over + ['name' => 'Bashundhara LP Gas', 'kind' => 'GAS', 'phone' => '01711000111'])->assertCreated()->json('id');
    }

    public function test_a_supplier_is_created_once_and_a_duplicate_is_refused(): void
    {
        $api = $this->actingAs($this->admin, 'sanctum');
        $this->supplier();

        $api->postJson('/api/supplier-master', ['name' => ' bashundhara  lp gas ', 'kind' => 'GAS'])->assertStatus(422)->assertJsonValidationErrors('name');
        $api->postJson('/api/supplier-master', ['name' => 'Bashundhara LP Gas', 'kind' => 'MATERIALS'])->assertCreated(); // another kind is a different account
        $api->postJson('/api/supplier-master', ['kind' => 'GAS'])->assertStatus(422);
        $this->assertCount(1, $api->getJson('/api/supplier-master?kind=GAS')->json());
    }

    public function test_one_45_kg_cylinder_at_7700_is_a_purchase_of_exactly_7700(): void
    {
        $id = $this->supplier();
        $api = $this->actingAs($this->admin, 'sanctum');

        $p = $api->postJson('/api/gas/purchases', [
            'date' => now()->toDateString(), 'supplier_id' => $id, 'cylinders' => 1, 'cylinder_kg' => 45, 'price_per_cylinder' => 7700, 'treasury_medium' => 'CASH',
        ])->assertSuccessful()->json();

        $this->assertSame('Bashundhara LP Gas', $p['supplier_name']);            // the name comes from the supplier
        $this->assertSame('45.0000', $p['quantity_kg']);
        $this->assertSame('7700.0000', $p['total_cost']);                         // not 45 x a rounded per-kg rate
        $this->assertSame('171.1111', $p['unit_price']);                          // the per-kg rate follows from it
        $this->assertSame('7700.0000', (string) GasPurchase::first()->expense->amount);
    }

    public function test_several_cylinders_and_transport_add_up(): void
    {
        $id = $this->supplier();

        $p = $this->actingAs($this->admin, 'sanctum')->postJson('/api/gas/purchases', [
            'date' => now()->toDateString(), 'supplier_id' => $id, 'cylinders' => 4, 'cylinder_kg' => 45, 'price_per_cylinder' => 7700, 'transport_cost' => 300, 'treasury_medium' => 'BANK',
        ])->assertSuccessful()->json();

        $this->assertSame('180.0000', $p['quantity_kg']);
        $this->assertSame('31100.0000', $p['total_cost']); // 4 x 7700 + 300
    }

    public function test_the_old_kg_and_rate_way_still_works_and_a_supplier_is_needed_one_way_or_the_other(): void
    {
        $api = $this->actingAs($this->admin, 'sanctum');

        $api->postJson('/api/gas/purchases', ['date' => now()->toDateString(), 'supplier_name' => 'Typed Name', 'quantity_kg' => 12, 'unit_price' => 170, 'treasury_medium' => 'CASH'])->assertSuccessful()->assertJsonPath('total_cost', '2040.0000');
        $api->postJson('/api/gas/purchases', ['date' => now()->toDateString(), 'cylinders' => 1, 'cylinder_kg' => 45, 'price_per_cylinder' => 7700, 'treasury_medium' => 'CASH'])->assertStatus(422)->assertJsonValidationErrors('supplier_name');
        $api->postJson('/api/gas/purchases', ['date' => now()->toDateString(), 'supplier_name' => 'X', 'cylinders' => 1, 'treasury_medium' => 'CASH'])->assertStatus(422);
    }

    public function test_only_a_supplier_of_the_right_kind_that_is_switched_on_can_be_picked(): void
    {
        $api = $this->actingAs($this->admin, 'sanctum');
        $mat = $this->supplier(['name' => 'Brick Co', 'kind' => 'MATERIALS']);
        $gas = $this->supplier(['name' => 'Gas Co']);
        $body = fn ($id) => ['date' => now()->toDateString(), 'supplier_id' => $id, 'cylinders' => 1, 'cylinder_kg' => 45, 'price_per_cylinder' => 7700, 'treasury_medium' => 'CASH'];

        $api->postJson('/api/gas/purchases', $body($mat))->assertStatus(422)->assertJsonValidationErrors('supplier_id');
        $api->putJson("/api/supplier-master/{$gas}", ['is_active' => false])->assertOk();
        $api->postJson('/api/gas/purchases', $body($gas))->assertStatus(422)->assertJsonValidationErrors('supplier_id');
    }

    public function test_a_new_supplier_shows_in_the_supplier_ledger_before_anything_is_bought(): void
    {
        $this->supplier(['name' => 'Fresh Gas Supplier']);
        $api = $this->actingAs($this->admin, 'sanctum');

        $rows = $api->getJson('/api/gas/suppliers')->assertOk()->json('suppliers');
        $this->assertSame('Fresh Gas Supplier', $rows[0]['name']);
        $this->assertSame('0.0000', $rows[0]['owed']);

        // once bought from, it is one account, not two
        $api->postJson('/api/gas/purchases', ['date' => now()->toDateString(), 'supplier_id' => $this->supplierId('Fresh Gas Supplier'), 'cylinders' => 1, 'cylinder_kg' => 45, 'price_per_cylinder' => 7700, 'treasury_medium' => 'CASH'])->assertSuccessful();
        $rows = $api->getJson('/api/gas/suppliers')->json('suppliers');
        $this->assertCount(1, $rows);
        $this->assertSame('7700.0000', $rows[0]['owed']);
    }

    private function supplierId(string $name): int
    {
        return (int) \App\Models\Supplier::where('name', $name)->value('id');
    }
}
