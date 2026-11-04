<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\ContributionSeeder;
use Database\Seeders\ExpenseHeadSeeder;
use Database\Seeders\MoneyCorePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The home-screen overview: real numbers, and only the blocks the caller is allowed to see. */
class DashboardOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(ExpenseHeadSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(MoneyCorePermissionSeeder::class);
        $this->seed(ContributionSeeder::class);
        $this->seed(\Database\Seeders\CommunityHallSeeder::class);
        $this->seed(\Database\Seeders\RoleCatalogSeeder::class);
        $this->seed(\Database\Seeders\ParkingSeeder::class);
        $this->seed(\Database\Seeders\GovernancePermissionSeeder::class);
        $this->seed(\Database\Seeders\FlatMasterPermissionSeeder::class);
    }

    public function test_admin_gets_every_block(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $res = $this->actingAs($admin, 'sanctum')->getJson('/api/dashboard/overview')->assertOk();

        foreach (['billing', 'funds', 'approvals', 'recent_payments', 'gas', 'parking', 'hall', 'complaints', 'contributions', 'supplier_owed', 'flats', 'headline'] as $key) {
            $this->assertArrayHasKey($key, $res->json(), "missing block {$key}");
        }
        $this->assertCount(6, $res->json('billing.trend'));
        $this->assertCount(4, $res->json('funds.funds'));
    }

    public function test_a_user_without_permissions_gets_no_blocks(): void
    {
        $nobody = User::factory()->create(['role' => 'tenant']);

        $res = $this->actingAs($nobody, 'sanctum')->getJson('/api/dashboard/overview')->assertOk();

        foreach (['billing', 'funds', 'gas', 'parking', 'contributions', 'supplier_owed'] as $key) {
            $this->assertArrayNotHasKey($key, $res->json());
        }
    }

    public function test_requires_login(): void
    {
        $this->getJson('/api/dashboard/overview')->assertUnauthorized();
    }

    public function test_the_headline_shows_service_project_and_gas_standing(): void
    {
        $this->seed(\Database\Seeders\TreasurySeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        \App\Models\BankAccount::create(['bank_name' => 'B', 'account_no' => '1', 'account_title' => 'GV']);
        $treasury = app(\App\Services\TreasuryService::class);
        $treasury->openingBalance('OPERATING', '80000', '20000', '2026-01-01', null, $admin->id);
        $treasury->openingBalance('CAPITAL', '50000', '0', '2026-01-01', null, $admin->id);

        // one flat with a bill of 5000 (gas 1000) of which 2000 has been paid: 3000 is owed, a fifth of it gas
        $flat = \App\Models\Flat::factory()->create();
        $period = \App\Models\BillingPeriod::create(['year' => now()->year, 'month' => now()->month, 'status' => 'GENERATED']);
        \App\Models\Bill::create([
            'billing_period_id' => $period->id, 'flat_id' => $flat->id, 'service_charge' => 4000, 'gas_amount' => 1000, 'other_charges' => 0, 'late_fee' => 0,
            'previous_due' => 0, 'total_payable' => 5000, 'paid_amount' => 2000, 'due_date' => now()->toDateString(), 'status' => 'PARTIAL',
        ]);

        $h = $this->actingAs($admin, 'sanctum')->getJson('/api/dashboard/overview')->assertOk()->json('headline');

        $this->assertSame('100000.0000', $h['service']['balance']);   // 80000 cash + 20000 bank
        $this->assertSame('2400.0000', $h['service']['receivable']);  // 3000 owed, less its 600 gas share
        $this->assertSame('50000.0000', $h['project']['balance']);
        $this->assertSame('600.0000', $h['gas']['receivable']);
        $this->assertSame('0.0000', $h['gas']['payable']);
        $this->assertSame('600.0000', $h['gas']['net']);
    }
}
