<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\User;
use App\Services\TreasuryService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\CommunityHallSeeder;
use Database\Seeders\ExpenseHeadSeeder;
use Database\Seeders\MoneyCorePermissionSeeder;
use Database\Seeders\RoleCatalogSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TreasurySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Service charge + gas (or any funds) in ONE statement document. */
class CombinedStatementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([ChartOfAccountsSeeder::class, RoleSeeder::class, MoneyCorePermissionSeeder::class, ExpenseHeadSeeder::class, CommunityHallSeeder::class, RoleCatalogSeeder::class, TreasurySeeder::class] as $seeder) {
            $this->seed($seeder);
        }
        BankAccount::create(['bank_name' => 'Test Bank', 'account_title' => 'GV', 'account_no' => '1', 'opening_balance' => 0, 'current_balance' => 0]);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->assignRole('admin');
        app(TreasuryService::class)->openingBalance('OPERATING', '100000', '5000', '2026-09-01', null, $this->admin->id);
        app(TreasuryService::class)->openingBalance('GAS', '30000', '0', '2026-09-01', null, $this->admin->id);
    }

    public function test_service_charge_and_gas_come_out_as_one_pdf_by_default(): void
    {
        $res = $this->actingAs($this->admin, 'sanctum')->get('/api/treasury/statement/combined/pdf?year=2026&month=9')->assertOk();
        $pdf = $res->getContent();

        $this->assertStringStartsWith('%PDF', $pdf);
        // two funds plus a summary page are clearly more than one fund's statement alone
        $single = $this->actingAs($this->admin, 'sanctum')->get('/api/treasury/statement/pdf?fund=GAS&year=2026&month=9')->assertOk()->getContent();
        $this->assertGreaterThan(strlen($single) + 2000, strlen($pdf));
    }

    public function test_the_funds_can_be_chosen_and_all_four_work(): void
    {
        $api = $this->actingAs($this->admin, 'sanctum');
        $api->get('/api/treasury/statement/combined/pdf?year=2026&month=9&funds[]=OPERATING&funds[]=GAS&funds[]=CAPITAL&funds[]=SINKING')->assertOk();
        $api->get('/api/treasury/statement/combined/pdf?year=2026&month=9&funds[]=SINKING')->assertOk();
    }

    public function test_bad_input_is_rejected_and_permission_is_needed(): void
    {
        $api = $this->actingAs($this->admin, 'sanctum');
        $api->getJson('/api/treasury/statement/combined/pdf?year=2026&month=9&funds[]=NOPE')->assertStatus(422);
        $api->getJson('/api/treasury/statement/combined/pdf?year=2026&month=9&funds[]=GAS&funds[]=GAS')->assertStatus(422);
        $api->getJson('/api/treasury/statement/combined/pdf?month=9')->assertStatus(422);

        $tenant = User::factory()->create(['role' => 'tenant']);
        $this->actingAs($tenant, 'sanctum')->getJson('/api/treasury/statement/combined/pdf?year=2026&month=9')->assertForbidden();
    }
}
