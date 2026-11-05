<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\ExpenseHeadSeeder;
use Database\Seeders\MoneyCorePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Suppliers come in many categories; the ones outside gas and materials are paid through ordinary expenses. */
class SupplierCategoriesTest extends TestCase
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

    private function supplier(string $name, string $kind): int
    {
        return $this->actingAs($this->admin, 'sanctum')->postJson('/api/supplier-master', ['name' => $name, 'kind' => $kind])->assertCreated()->json('id');
    }

    private function spend(string $payee, string $amount, string $status = 'PENDING'): Expense
    {
        $e = Expense::create([
            'date' => now()->toDateString(), 'expense_head_id' => ExpenseHead::where('code', 'MAINTENANCE')->value('id'), 'payee' => $payee,
            'amount' => $amount, 'description' => 'Lift repair', 'treasury_medium' => 'CASH', 'fund_type' => 'OPERATING', 'status' => $status,
            'created_by' => $this->admin->id,
        ]);

        return $e;
    }

    public function test_many_categories_can_be_created_and_each_carries_its_label(): void
    {
        $api = $this->actingAs($this->admin, 'sanctum');
        $r = $api->postJson('/api/supplier-master', ['name' => 'Otis Service', 'kind' => 'LIFT'])->assertCreated();
        $this->assertSame('লিফট', $r->json('kind_label'));
        foreach (['ELECTRIC', 'PLUMBING', 'GENERATOR', 'CLEANING', 'SECURITY', 'REPAIR', 'OFFICE', 'OTHER'] as $k) {
            $api->postJson('/api/supplier-master', ['name' => "S {$k}", 'kind' => $k])->assertCreated();
        }
        $api->postJson('/api/supplier-master', ['name' => 'Bad', 'kind' => 'NONSENSE'])->assertStatus(422)->assertJsonValidationErrors('kind');
        $this->assertCount(1, $api->getJson('/api/supplier-master?kind=LIFT')->json());
    }

    public function test_a_lift_supplier_cannot_be_picked_for_gas(): void
    {
        $id = $this->supplier('Otis Service', 'LIFT');

        $this->actingAs($this->admin, 'sanctum')->postJson('/api/gas/purchases', [
            'date' => now()->toDateString(), 'supplier_id' => $id, 'cylinders' => 1, 'cylinder_kg' => 45, 'price_per_cylinder' => 7700, 'treasury_medium' => 'CASH',
        ])->assertStatus(422)->assertJsonValidationErrors('supplier_id');
    }

    public function test_the_other_suppliers_book_shows_expenses_paid_to_registered_suppliers(): void
    {
        $this->supplier('Otis Service', 'LIFT');
        $this->spend('otis  service', '5000', 'APPROVED');
        $this->spend('Otis Service', '1200');
        $this->spend('Someone Unregistered', '999', 'APPROVED');
        $this->supplier('Brite Cleaners', 'CLEANING');
        $api = $this->actingAs($this->admin, 'sanctum');

        $list = $api->getJson('/api/other-suppliers')->assertOk()->json();
        $rows = collect($list['suppliers'])->keyBy('name');
        $this->assertCount(2, $rows);                                  // the unregistered payee is not a supplier
        $this->assertSame('6200.0000', $rows['Otis Service']['billed']);
        $this->assertSame('5000.0000', $rows['Otis Service']['paid']);
        $this->assertSame('1200.0000', $rows['Otis Service']['owed']); // still waiting for approval
        $this->assertSame('LIFT', $rows['Otis Service']['category']);
        $this->assertSame('0.0000', $rows['Brite Cleaners']['owed']);

        $ledger = $api->getJson('/api/other-suppliers/ledger?supplier='.urlencode('Otis Service'))->assertOk()->json();
        $this->assertSame('1200.0000', $ledger['totals']['closing']);
        $this->assertSame('Lift repair', $ledger['rows'][0]['particulars']);
        $api->get('/api/other-suppliers/ledger/pdf?supplier='.urlencode('Otis Service'))->assertOk();
        $api->get('/api/other-suppliers/ledger/excel?supplier='.urlencode('Otis Service'))->assertOk();
    }

    public function test_the_other_suppliers_book_needs_the_expense_view_permission(): void
    {
        $nobody = User::factory()->create(['role' => 'resident']);

        $this->actingAs($nobody, 'sanctum')->getJson('/api/other-suppliers')->assertForbidden();
    }
}
