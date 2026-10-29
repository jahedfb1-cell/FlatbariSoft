<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\User;
use App\Services\GasBillingService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\ExpenseHeadSeeder;
use Database\Seeders\MoneyCorePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Owner-requested (2026-09): dedicated purchase-history tracking for gas
 * (fills a gap — gas_purchases existed since Phase 1.5 with no way to
 * create one) and construction materials (new, "Gas-এর মতো dedicated
 * tracking ... stock না, শুধু history/report"). Both route the money side
 * through the existing ExpenseService/AccountingService so the ledger
 * stays the single source of truth (BR-23-style rule for this project).
 */
class MaterialPurchasesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(ExpenseHeadSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(MoneyCorePermissionSeeder::class);
    }

    private function manager(): User
    {
        $user = User::factory()->create(['role' => 'manager']);
        $user->assignRole('manager');

        return $user;
    }

    private function admin(): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        $user->assignRole('admin');

        return $user;
    }

    // --- Gas purchases -----------------------------------------------

    public function test_manager_can_record_a_gas_purchase_which_creates_a_pending_expense(): void
    {
        $manager = $this->manager();

        $response = $this->actingAs($manager, 'sanctum')->postJson('/api/gas/purchases', [
            'date' => '2026-08-01',
            'supplier_name' => 'ABC Gas Supplier',
            'quantity_kg' => 500,
            'unit_price' => 90,
            'transport_cost' => 500,
            'treasury_medium' => 'CASH',
        ]);

        // 500*90 + 500 = 45500
        $response->assertStatus(201)->assertJson([
            'supplier_name' => 'ABC Gas Supplier',
            'quantity_kg' => '500.0000',
            'total_cost' => '45500.0000',
            'expense_status' => 'PENDING',
        ]);
        $this->assertDatabaseHas('expenses', ['payee' => 'ABC Gas Supplier', 'fund_type' => 'GAS', 'amount' => 45500]);
        // Nothing posted to the ledger yet — only approving the expense does that.
        $this->assertDatabaseMissing('cash_ledger', ['out_amount' => 45500]);
    }

    public function test_a_role_without_gas_permission_cannot_record_a_purchase(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant']);
        $tenant->assignRole('tenant');

        $this->actingAs($tenant, 'sanctum')->postJson('/api/gas/purchases', [
            'date' => '2026-08-01', 'supplier_name' => 'X', 'quantity_kg' => 100,
            'unit_price' => 90, 'treasury_medium' => 'CASH',
        ])->assertStatus(403);
    }

    public function test_gas_purchase_validates_required_fields(): void
    {
        $manager = $this->manager();

        $this->actingAs($manager, 'sanctum')->postJson('/api/gas/purchases', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date', 'supplier_name', 'quantity_kg', 'unit_price', 'treasury_medium']);
    }

    public function test_approving_the_linked_expense_posts_the_gas_purchase_to_the_ledger(): void
    {
        $manager = $this->manager();
        $admin = $this->admin();

        $this->actingAs($manager, 'sanctum')->postJson('/api/gas/purchases', [
            'date' => '2026-08-01', 'supplier_name' => 'ABC Gas Supplier',
            'quantity_kg' => 500, 'unit_price' => 90, 'treasury_medium' => 'CASH',
        ])->assertStatus(201);

        $purchase = \App\Models\GasPurchase::first();
        $this->actingAs($admin, 'sanctum')->postJson("/api/expenses/{$purchase->expense_id}/approve", ["note" => "Checked the bill"])->assertStatus(200);

        $this->assertDatabaseHas('cash_ledger', ['out_amount' => 45000]);
        $this->assertSame('APPROVED', $purchase->expense->fresh()->status);
    }

    public function test_gas_purchase_quantity_feeds_system_loss_calculation(): void
    {
        $manager = $this->manager();
        \App\Models\BillingPeriod::create(['year' => 2026, 'month' => 8, 'status' => 'GENERATED']);

        $this->actingAs($manager, 'sanctum')->postJson('/api/gas/purchases', [
            'date' => '2026-08-01', 'supplier_name' => 'ABC Gas Supplier',
            'quantity_kg' => 500, 'unit_price' => 90, 'treasury_medium' => 'CASH',
        ])->assertStatus(201);

        $period = \App\Models\BillingPeriod::where('year', 2026)->where('month', 8)->first();
        $lossPercent = app(GasBillingService::class)->systemLossPercent(2026, 8, $period->id);

        // No readings recorded -> 0 units billed against 500kg purchased = 100% "loss",
        // the point being the calculation now has real purchased-quantity data at all.
        $this->assertSame(100.0, $lossPercent);
    }

    // --- Construction material purchases ------------------------------

    public function test_manager_can_record_a_construction_material_purchase(): void
    {
        $manager = $this->manager();

        $response = $this->actingAs($manager, 'sanctum')->postJson('/api/construction-materials', [
            'date' => '2026-08-01',
            'item_name' => 'Cement',
            'quantity' => 100,
            'unit' => 'bag',
            'unit_rate' => 550,
            'supplier_name' => 'Bashundhara Cement',
            'transport_cost' => 1000,
            'treasury_medium' => 'BANK',
        ]);

        // 100*550 + 1000 = 56000
        $response->assertStatus(201)->assertJson([
            'item_name' => 'Cement',
            'unit' => 'bag',
            'total_cost' => '56000.0000',
            'expense_status' => 'PENDING',
        ]);
        $this->assertDatabaseHas('expenses', ['payee' => 'Bashundhara Cement', 'fund_type' => 'CAPITAL', 'amount' => 56000]);
    }

    public function test_construction_material_purchase_validates_required_fields(): void
    {
        $manager = $this->manager();

        $this->actingAs($manager, 'sanctum')->postJson('/api/construction-materials', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date', 'item_name', 'quantity', 'unit', 'unit_rate', 'treasury_medium']);
    }

    public function test_a_role_without_expense_permission_cannot_record_a_material_purchase(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant']);
        $tenant->assignRole('tenant');

        $this->actingAs($tenant, 'sanctum')->postJson('/api/construction-materials', [
            'date' => '2026-08-01', 'item_name' => 'Rod', 'quantity' => 10, 'unit' => 'ton',
            'unit_rate' => 90000, 'treasury_medium' => 'CASH',
        ])->assertStatus(403);
    }

    public function test_approving_the_linked_expense_posts_the_material_purchase_to_the_bank_ledger(): void
    {
        $manager = $this->manager();
        $admin = $this->admin();
        BankAccount::create(['bank_name' => 'Test Bank', 'account_no' => '1', 'account_title' => 'GV']);

        $this->actingAs($manager, 'sanctum')->postJson('/api/construction-materials', [
            'date' => '2026-08-01', 'item_name' => 'Rod', 'quantity' => 1,
            'unit' => 'ton', 'unit_rate' => 90000, 'treasury_medium' => 'BANK',
        ])->assertStatus(201);

        $expenseId = \App\Models\ConstructionMaterialPurchase::first()->expense_id;
        $this->actingAs($admin, 'sanctum')->postJson("/api/expenses/{$expenseId}/approve", ["note" => "Checked the bill"])->assertStatus(200);

        $this->assertDatabaseHas('bank_transactions', ['credit' => 90000]);
        $this->assertSame('APPROVED', \App\Models\ConstructionMaterialPurchase::first()->expense->fresh()->status);
    }

    public function test_material_purchase_list_includes_linked_expense_status(): void
    {
        $manager = $this->manager();

        $this->actingAs($manager, 'sanctum')->postJson('/api/construction-materials', [
            'date' => '2026-08-01', 'item_name' => 'Sand', 'quantity' => 200,
            'unit' => 'cft', 'unit_rate' => 25, 'treasury_medium' => 'CASH',
        ])->assertStatus(201);

        $response = $this->actingAs($manager, 'sanctum')->getJson('/api/construction-materials');

        $response->assertStatus(200)->assertJsonFragment(['item_name' => 'Sand', 'expense_status' => 'PENDING']);
    }
}
