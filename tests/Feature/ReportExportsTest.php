<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\ChartOfAccount;
use App\Models\User;
use App\Services\AccountingService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\MoneyCorePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Owner-requested (2026-09): every report can be exported as a PDF or an
 * Excel file, on top of the existing JSON used by the SPA's on-screen
 * view. PDFs come out English-labelled — dompdf has no text-shaping
 * engine, so it cannot correctly render Bengali script (pre-base vowel
 * signs land in the wrong visual position) no matter which font is
 * embedded; verified by hand before writing this. Excel files keep
 * Bengali labels since a spreadsheet app does its own text shaping.
 */
class ReportExportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(MoneyCorePermissionSeeder::class);
        Building::create(['name' => 'Green Valley', 'address' => 'Test', 'total_floors' => 10, 'total_flats' => 45]);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        $user->assignRole('admin');

        return $user;
    }

    private function postSomeJournal(User $admin): void
    {
        $cash = ChartOfAccount::where('code', '1200')->first();
        $income = ChartOfAccount::where('code', '4100')->first();
        app(AccountingService::class)->postJournal(
            [
                ['account_id' => $cash->id, 'debit' => 1000],
                ['account_id' => $income->id, 'credit' => 1000],
            ],
            'Test entry',
            '2026-08-01',
            $admin->id,
            $admin->id,
        );
    }

    public function test_trial_balance_pdf_downloads_successfully(): void
    {
        $admin = $this->admin();
        $this->postSomeJournal($admin);

        $response = $this->actingAs($admin, 'sanctum')->get('/api/reports/trial-balance/pdf');

        $response->assertStatus(200);
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_trial_balance_excel_downloads_successfully(): void
    {
        $admin = $this->admin();
        $this->postSomeJournal($admin);

        $response = $this->actingAs($admin, 'sanctum')->get('/api/reports/trial-balance/excel');

        $response->assertStatus(200);
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('Content-Type'));
    }

    public function test_balance_sheet_pdf_downloads_successfully(): void
    {
        $admin = $this->admin();
        $this->postSomeJournal($admin);

        $this->actingAs($admin, 'sanctum')->get('/api/reports/balance-sheet/pdf')->assertStatus(200);
    }

    public function test_balance_sheet_excel_downloads_successfully(): void
    {
        $admin = $this->admin();
        $this->postSomeJournal($admin);

        $this->actingAs($admin, 'sanctum')->get('/api/reports/balance-sheet/excel')->assertStatus(200);
    }

    public function test_income_statement_pdf_defaults_to_the_current_fiscal_year(): void
    {
        $admin = $this->admin();
        $this->postSomeJournal($admin);

        // No fiscal_year query param — controller falls back to
        // AccountingService::fiscalYearFor(now()) rather than requiring it.
        $this->actingAs($admin, 'sanctum')->get('/api/reports/income-statement/pdf')->assertStatus(200);
    }

    public function test_income_statement_excel_defaults_to_the_current_fiscal_year(): void
    {
        $admin = $this->admin();
        $this->postSomeJournal($admin);

        $this->actingAs($admin, 'sanctum')->get('/api/reports/income-statement/excel')->assertStatus(200);
    }

    public function test_report_exports_require_accounting_view_permission(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant']);
        $tenant->assignRole('tenant');

        $this->actingAs($tenant, 'sanctum')->get('/api/reports/trial-balance/pdf')->assertStatus(403);
        $this->actingAs($tenant, 'sanctum')->get('/api/reports/trial-balance/excel')->assertStatus(403);
    }

    public function test_report_exports_require_authentication(): void
    {
        $this->getJson('/api/reports/trial-balance/pdf')->assertStatus(401);
    }

    public function test_pdf_still_works_on_a_fresh_deploy_where_the_font_cache_folder_does_not_exist(): void
    {
        // A fresh clone/deploy has no storage/app/dompdf-font-cache; dompdf alone dies on that.
        $cache = (string) config('dompdf.options.font_cache');
        File::deleteDirectory($cache);
        $this->assertDirectoryDoesNotExist($cache);

        $admin = $this->admin();
        $this->postSomeJournal($admin);
        // a real request boots the app first, and that boot recreates the folder
        (new \App\Providers\AppServiceProvider($this->app))->boot();
        $this->assertDirectoryExists($cache);
        File::deleteDirectory($cache);
        (new \App\Providers\AppServiceProvider($this->app))->boot();

        $this->actingAs($admin, 'sanctum')->get('/api/reports/trial-balance/pdf')->assertStatus(200);
        $this->assertDirectoryExists($cache);
    }
}
