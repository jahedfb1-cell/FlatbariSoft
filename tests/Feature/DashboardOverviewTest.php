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

        foreach (['billing', 'funds', 'approvals', 'recent_payments', 'gas', 'parking', 'hall', 'complaints', 'contributions', 'supplier_owed', 'flats'] as $key) {
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
}
