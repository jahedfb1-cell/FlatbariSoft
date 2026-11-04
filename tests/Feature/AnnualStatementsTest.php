<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Bill;
use App\Models\BillingPeriod;
use App\Models\ExpenseHead;
use App\Models\Flat;
use App\Models\User;
use App\Services\AnnualStatementService;
use App\Services\ExpenseService;
use App\Services\PaymentService;
use App\Services\TreasuryService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\ExpenseHeadSeeder;
use Database\Seeders\MoneyCorePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TreasurySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The year-end statements: cash flow, income and expenditure, balance sheet - worked out from the books. */
class AnnualStatementsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $checker;

    private int $fy;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([ChartOfAccountsSeeder::class, ExpenseHeadSeeder::class, RoleSeeder::class, MoneyCorePermissionSeeder::class, TreasurySeeder::class] as $s) {
            $this->seed($s);
        }
        foreach (['admin', 'checker'] as $k) {
            $this->{$k} = User::factory()->create(['role' => 'admin']);
            $this->{$k}->assignRole('admin');
        }
        $now = now('Asia/Dhaka');
        $this->fy = $now->month >= 7 ? $now->year : $now->year - 1;
        BankAccount::create(['bank_name' => 'B', 'account_no' => '1', 'account_title' => 'GV']);

        // the money the year starts with: 50,000 cash and 100,000 in the bank
        app(TreasuryService::class)->openingBalance('OPERATING', '50000', '100000', "{$this->fy}-07-01", null, $this->admin->id);

        // a flat billed 4,000 service + 1,000 gas for this month; it pays 3,000 in cash, 2,000 stays due
        $flat = Flat::factory()->create(['flat_no' => '5A']);
        $period = BillingPeriod::firstOrCreate(['year' => $now->year, 'month' => $now->month], ['status' => 'GENERATED']);
        $bill = Bill::create([
            'billing_period_id' => $period->id, 'flat_id' => $flat->id, 'service_charge' => 4000, 'gas_amount' => 1000, 'other_charges' => 0, 'late_fee' => 0,
            'previous_due' => 0, 'total_payable' => 5000, 'paid_amount' => 0, 'due_date' => $now->toDateString(), 'status' => 'UNPAID',
        ]);
        $payments = app(PaymentService::class);
        $p = $payments->create(['flat_id' => $flat->id, 'bill_id' => $bill->id, 'amount' => 3000, 'payment_date' => $now->toDateString(), 'method' => 'CASH'], $this->admin->id);
        $payments->approve($p, $this->checker->id);

        // an approved electricity expense of 1,200 paid in cash
        $expenses = app(ExpenseService::class);
        $e = $expenses->create(['date' => $now->toDateString(), 'expense_head_id' => ExpenseHead::where('code', 'UTILITY')->first()->id, 'payee' => 'DESCO', 'amount' => 1200, 'treasury_medium' => 'CASH'], $this->admin->id);
        $expenses->approve($e, $this->checker->id, 'ok');
    }

    public function test_the_cash_flow_runs_from_the_opening_to_the_closing_balance_and_agrees_with_the_books(): void
    {
        $cf = app(AnnualStatementService::class)->build($this->fy)['cash_flow'];

        $this->assertSame('150000.00', $cf['opening']);              // 50,000 cash + 100,000 bank brought in
        $this->assertSame('3000.00', $cf['total_receipts']);          // only what really came in
        $this->assertSame('1200.00', $cf['total_payments']);
        $this->assertSame('1800.00', $cf['net']);
        $this->assertSame('151800.00', $cf['closing']);
        $this->assertTrue($cf['agrees_with_books']);
        $this->assertSame('100000.00', $cf['closing_bank']);
        $labels = array_column($cf['receipts'], 'amount', 'label');
        $this->assertSame('2400.00', $labels['সার্ভিস চার্জ আদায়']); // payments are split service / gas the way the books split them
        $this->assertSame('600.00', $labels['গ্যাস বিল আদায়']);
    }

    public function test_income_is_what_was_billed_and_spending_what_was_incurred_so_dues_are_in(): void
    {
        $ie = app(AnnualStatementService::class)->build($this->fy)['income_expenditure'];

        $income = array_column($ie['income'], 'amount', 'label');
        $this->assertSame('4000.00', $income['সার্ভিস চার্জ (বিল করা)']);   // billed, although 2,000 of the bill is still unpaid
        $this->assertSame('1000.00', $income['গ্যাস বিল (বিল করা)']);
        $this->assertSame('5000.00', $ie['total_income']);
        $this->assertSame('1200.00', $ie['total_expenditure']);
        $this->assertSame('3800.00', $ie['surplus']);                       // more than the cash flow's 1,800: the dues are not yet cash
        $this->assertSame('5000.00', $ie['memo']['billed']);
        $this->assertSame('3000.00', $ie['memo']['collected']);
        $this->assertSame(60.0, $ie['memo']['collection_percent']);
    }

    public function test_the_balance_sheet_shows_cash_bank_dues_and_balances(): void
    {
        $bs = app(AnnualStatementService::class)->build($this->fy)['balance_sheet'];

        $assets = array_column($bs['assets'], 'amount', 'label');
        $this->assertSame('51800.00', $assets['হাতে নগদ']);                 // 50,000 + 3,000 - 1,200
        $this->assertSame('100000.00', $assets['ব্যাংকে জমা']);
        $this->assertSame('2000.00', $assets['ফ্ল্যাটদের কাছে বকেয়া পাওনা']);
        $this->assertSame('153800.00', $bs['total_assets']);
        $this->assertTrue($bs['balanced']);
        $this->assertSame($bs['total_assets'], $bs['total_liabilities_and_funds']);
        $this->assertSame('2000.00', $bs['accrued']['amount']);
    }

    public function test_the_year_can_be_viewed_as_json_pdf_and_excel_and_only_by_people_who_may(): void
    {
        $api = $this->actingAs($this->admin, 'sanctum');

        $api->getJson("/api/reports/annual?fiscal_year={$this->fy}")->assertOk()->assertJsonPath('label', $this->fy.'-'.substr((string) ($this->fy + 1), 2))
            ->assertJsonStructure(['cash_flow', 'income_expenditure', 'balance_sheet']);
        $this->assertStringContainsString('pdf', $api->get("/api/reports/annual/pdf?fiscal_year={$this->fy}")->assertOk()->headers->get('content-type'));
        $api->get("/api/reports/annual/excel?fiscal_year={$this->fy}")->assertOk();

        $tenant = User::factory()->create(['role' => 'tenant']);
        $tenant->assignRole('tenant');
        $this->actingAs($tenant, 'sanctum')->getJson('/api/reports/annual')->assertForbidden();
    }

    public function test_a_year_with_nothing_in_it_is_all_zeros_not_an_error(): void
    {
        $r = app(AnnualStatementService::class)->build($this->fy - 2);

        $this->assertSame('0.00', $r['cash_flow']['total_receipts']);
        $this->assertSame('0.00', $r['income_expenditure']['surplus']);
        $this->assertTrue($r['cash_flow']['agrees_with_books']);
    }
}
