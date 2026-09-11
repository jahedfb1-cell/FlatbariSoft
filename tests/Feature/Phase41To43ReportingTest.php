<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\BillingPeriod;
use App\Models\ExpenseHead;
use App\Models\Flat;
use App\Models\User;
use App\Services\ExpenseService;
use App\Services\PaymentService;
use App\Services\ReportingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Phase41To43ReportingTest extends TestCase
{
    use RefreshDatabase;

    private ReportingService $reporting;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reporting = app(ReportingService::class);
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);
        $this->seed(\Database\Seeders\ExpenseHeadSeeder::class);
    }

    public function test_trial_balance_is_always_balanced_after_real_transactions(): void
    {
        $user = User::factory()->create();
        $flat = Flat::factory()->create(['occupancy_status' => 'OWNER_OCCUPIED', 'service_charge_rate' => 3500]);
        $period = BillingPeriod::create(['year' => 2026, 'month' => 8, 'status' => 'GENERATED']);
        $bill = Bill::create([
            'billing_period_id' => $period->id, 'flat_id' => $flat->id,
            'service_charge' => 3500, 'gas_amount' => 0, 'previous_due' => 0,
            'total_payable' => 3500, 'paid_amount' => 0, 'due_date' => '2026-08-10', 'status' => 'UNPAID',
        ]);

        $paymentService = app(PaymentService::class);
        $payment = $paymentService->create(['flat_id' => $flat->id, 'bill_id' => $bill->id, 'amount' => 3500, 'payment_date' => '2026-08-05', 'method' => 'CASH'], $user->id);
        $paymentService->approve($payment, $user->id);

        $expenseService = app(ExpenseService::class);
        $head = ExpenseHead::where('code', 'UTILITY')->first();
        $expense = $expenseService->create(['date' => '2026-08-06', 'expense_head_id' => $head->id, 'payee' => 'DESCO', 'amount' => 800, 'treasury_medium' => 'CASH'], $user->id);
        $expenseService->approve($expense, $user->id);

        $trialBalance = $this->reporting->trialBalance();

        $this->assertTrue($trialBalance['balanced']);
        $this->assertEquals($trialBalance['total_debit'], $trialBalance['total_credit']);
        $this->assertGreaterThan(0, $trialBalance['total_debit']);
    }

    public function test_income_statement_computes_net_surplus(): void
    {
        $user = User::factory()->create();
        $flat = Flat::factory()->create(['occupancy_status' => 'OWNER_OCCUPIED', 'service_charge_rate' => 3500]);
        $period = BillingPeriod::create(['year' => 2026, 'month' => 8, 'status' => 'GENERATED']);
        $bill = Bill::create([
            'billing_period_id' => $period->id, 'flat_id' => $flat->id,
            'service_charge' => 3500, 'gas_amount' => 0, 'previous_due' => 0,
            'total_payable' => 3500, 'paid_amount' => 0, 'due_date' => '2026-08-10', 'status' => 'UNPAID',
        ]);

        $paymentService = app(PaymentService::class);
        $payment = $paymentService->create(['flat_id' => $flat->id, 'bill_id' => $bill->id, 'amount' => 3500, 'payment_date' => '2026-08-05', 'method' => 'CASH'], $user->id);
        $paymentService->approve($payment, $user->id);

        $expenseService = app(ExpenseService::class);
        $head = ExpenseHead::where('code', 'UTILITY')->first();
        $expense = $expenseService->create(['date' => '2026-08-06', 'expense_head_id' => $head->id, 'payee' => 'DESCO', 'amount' => 800, 'treasury_medium' => 'CASH'], $user->id);
        $expenseService->approve($expense, $user->id);

        $statement = $this->reporting->incomeStatement('2026-2027');

        $this->assertSame(3500.0, $statement['total_income']);
        $this->assertSame(800.0, $statement['total_expense']);
        $this->assertSame(2700.0, $statement['net_surplus']);
    }

    public function test_balance_sheet_assets_equal_liabilities_plus_funds(): void
    {
        $user = User::factory()->create();
        $flat = Flat::factory()->create(['occupancy_status' => 'OWNER_OCCUPIED', 'service_charge_rate' => 3500]);
        $period = BillingPeriod::create(['year' => 2026, 'month' => 8, 'status' => 'GENERATED']);
        $bill = Bill::create([
            'billing_period_id' => $period->id, 'flat_id' => $flat->id,
            'service_charge' => 3500, 'gas_amount' => 0, 'previous_due' => 0,
            'total_payable' => 3500, 'paid_amount' => 0, 'due_date' => '2026-08-10', 'status' => 'UNPAID',
        ]);
        $paymentService = app(PaymentService::class);
        $payment = $paymentService->create(['flat_id' => $flat->id, 'bill_id' => $bill->id, 'amount' => 3500, 'payment_date' => '2026-08-05', 'method' => 'CASH'], $user->id);
        $paymentService->approve($payment, $user->id);

        $sheet = $this->reporting->balanceSheet();

        $this->assertTrue($sheet['balanced']);
    }

    public function test_dashboard_kpis_reflect_real_activity(): void
    {
        $user = User::factory()->create();
        $flat = Flat::factory()->create(['occupancy_status' => 'OWNER_OCCUPIED', 'service_charge_rate' => 3500]);
        $period = BillingPeriod::create(['year' => 2026, 'month' => 8, 'status' => 'GENERATED']);
        $bill = Bill::create([
            'billing_period_id' => $period->id, 'flat_id' => $flat->id,
            'service_charge' => 3500, 'gas_amount' => 0, 'previous_due' => 0,
            'total_payable' => 3500, 'paid_amount' => 0, 'due_date' => '2026-08-10', 'status' => 'UNPAID',
        ]);
        $paymentService = app(PaymentService::class);
        $payment = $paymentService->create(['flat_id' => $flat->id, 'bill_id' => $bill->id, 'amount' => 2000, 'payment_date' => '2026-08-05', 'method' => 'CASH'], $user->id);
        $paymentService->approve($payment, $user->id);

        $kpis = $this->reporting->dashboardKpis(2026, 8);

        $this->assertSame(2000.0, $kpis['collection']);
        $this->assertSame(1500.0, $kpis['outstanding_due']);
    }
}
