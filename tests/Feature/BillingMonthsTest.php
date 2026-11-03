<?php

namespace Tests\Feature;

use App\Models\BillingPeriod;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\MoneyCorePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The register can be opened for any month of the July-June year, billed or not. */
class BillingMonthsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fiscal_year_lists_all_twelve_months_billed_or_not(): void
    {
        foreach ([ChartOfAccountsSeeder::class, RoleSeeder::class, MoneyCorePermissionSeeder::class] as $s) {
            $this->seed($s);
        }
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        BillingPeriod::create(['year' => 2026, 'month' => 9, 'status' => 'DRAFT']);

        $res = $this->actingAs($admin, 'sanctum')->getJson('/api/billing/months?fiscal_year=2026')->assertOk();

        $res->assertJsonPath('label', '2026-27')->assertJsonCount(12, 'months');
        $res->assertJsonPath('months.0.month', 7)->assertJsonPath('months.0.year', 2026);
        $res->assertJsonPath('months.6.month', 1)->assertJsonPath('months.6.year', 2027);
        $res->assertJsonPath('months.11.month', 6)->assertJsonPath('months.11.year', 2027);
        $this->assertSame(0, $res->json('months.2.bill_count')); // September: a period but no bills yet
        $this->assertSame('0.0000', $res->json('months.0.due'));
    }
}
