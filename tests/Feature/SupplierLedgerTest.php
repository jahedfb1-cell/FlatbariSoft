<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ConstructionMaterialPurchaseService;
use App\Services\GasPurchaseService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\ExpenseHeadSeeder;
use Database\Seeders\MoneyCorePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Supplier accounts for gas and construction material: bought (Cr), paid (Dr), balance owed. */
class SupplierLedgerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(ExpenseHeadSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(MoneyCorePermissionSeeder::class);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->assignRole('admin');
    }

    private function gas(string $date, string $kg, string $rate, string $supplier)
    {
        return app(GasPurchaseService::class)->record([
            'date' => $date, 'supplier_name' => $supplier, 'quantity_kg' => $kg, 'unit_price' => $rate,
            'transport_cost' => '0', 'treasury_medium' => 'CASH',
        ], $this->admin->id);
    }

    private function material(string $date, string $item, string $qty, string $rate, string $supplier)
    {
        return app(ConstructionMaterialPurchaseService::class)->record([
            'date' => $date, 'item_name' => $item, 'quantity' => $qty, 'unit' => 'bag', 'unit_rate' => $rate,
            'supplier_name' => $supplier, 'transport_cost' => '0', 'treasury_medium' => 'CASH',
        ], $this->admin->id);
    }

    public function test_material_supplier_ledger_tracks_bought_paid_and_owed(): void
    {
        $a = $this->material('2026-09-01', 'Cement', '100', '500', 'Akij Traders');   // 50,000
        $b = $this->material('2026-09-10', 'Rod', '10', '1000', ' akij  traders ');   // 10,000 - same supplier
        $this->material('2026-09-12', 'Sand', '5', '200', 'Other Co');                  // 1,000
        $a->expense->update(['status' => 'APPROVED', 'voucher_no' => 'VOU-000001']);

        $res = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/construction-materials/suppliers/ledger?supplier=Akij Traders')->assertOk();

        $this->assertCount(3, $res->json('rows')); // 2 bills + 1 payment
        $this->assertSame('60000.0000', $res->json('totals.purchased'));
        $this->assertSame('50000.0000', $res->json('totals.paid'));
        $this->assertSame('10000.0000', $res->json('totals.closing'));
        $this->assertSame('10000.0000', collect($res->json('rows'))->last()['balance']);

        $list = $this->actingAs($this->admin, 'sanctum')->getJson('/api/construction-materials/suppliers')->assertOk();
        $this->assertCount(2, $list->json('suppliers'));
        $this->assertSame('Akij Traders', $list->json('suppliers.0.name'));
        $this->assertSame('11000.0000', $list->json('totals.owed'));
    }

    public function test_opening_balance_rolls_up_earlier_entries(): void
    {
        $this->material('2026-08-01', 'Brick', '1000', '10', 'Brick Co'); // 10,000 before the period
        $this->material('2026-09-05', 'Brick', '500', '10', 'Brick Co');  // 5,000 in period

        $res = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/construction-materials/suppliers/ledger?supplier=Brick Co&from=2026-09-01')->assertOk();

        $this->assertSame('10000.0000', $res->json('opening'));
        $this->assertSame('15000.0000', $res->json('totals.closing'));
        $this->assertCount(1, $res->json('rows'));
    }

    public function test_gas_and_material_accounts_are_kept_apart_and_gas_has_kg(): void
    {
        $this->gas('2026-09-01', '400', '95', 'Same Name');
        $this->material('2026-09-02', 'Cement', '1', '700', 'Same Name');

        $gas = $this->actingAs($this->admin, 'sanctum')->getJson('/api/gas/suppliers/ledger?supplier=Same Name')->assertOk();
        $this->assertSame('38000.0000', $gas->json('totals.closing'));
        $this->assertSame('400.0000', $gas->json('totals.quantity_kg'));

        $mat = $this->actingAs($this->admin, 'sanctum')->getJson('/api/construction-materials/suppliers/ledger?supplier=Same Name')->assertOk();
        $this->assertSame('700.0000', $mat->json('totals.closing'));
    }

    public function test_pdf_and_excel_and_permissions(): void
    {
        $this->material('2026-09-01', 'Cement', '10', '500', 'Akij');

        foreach (['pdf', 'excel'] as $fmt) {
            $this->actingAs($this->admin, 'sanctum')
                ->get("/api/construction-materials/suppliers/ledger/{$fmt}?supplier=Akij", ['Accept' => 'application/json'])->assertOk();
        }
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/gas/suppliers/ledger')->assertStatus(422);

        $nobody = User::factory()->create(['role' => 'tenant']);
        $this->actingAs($nobody, 'sanctum')->getJson('/api/construction-materials/suppliers')->assertForbidden();
        $this->actingAs($nobody, 'sanctum')->getJson('/api/gas/suppliers')->assertForbidden();
    }
}
