<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\BillingPeriod;
use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\Flat;
use App\Models\Payment;
use App\Models\User;
use App\Services\ExpenseService;
use App\Services\MonthlyClosingService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Phase17ExpensesClosingTest extends TestCase
{
    use RefreshDatabase;

    private ExpenseService $expenseService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->expenseService = app(ExpenseService::class);
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);
        $this->seed(\Database\Seeders\ExpenseHeadSeeder::class);
    }

    public function test_ordinary_expense_needs_only_one_approval(): void
    {
        $admin = User::factory()->create();
        $head = ExpenseHead::where('code', 'UTILITY')->first();

        $expense = $this->expenseService->create([
            'date' => '2026-08-05', 'expense_head_id' => $head->id, 'payee' => 'DESCO',
            'amount' => 5000, 'treasury_medium' => 'CASH',
        ], $admin->id);

        $approved = $this->expenseService->approve($expense, $admin->id);

        $this->assertSame('APPROVED', $approved->status);
        $this->assertNotNull($approved->voucher_no);
        $this->assertDatabaseHas('cash_ledger', ['out_amount' => 5000]);
    }

    public function test_sinking_fund_expense_over_25000_needs_two_distinct_approvers(): void
    {
        $admin1 = User::factory()->create();
        $admin2 = User::factory()->create();
        $head = ExpenseHead::where('code', 'MAINTENANCE')->first();

        $expense = $this->expenseService->create([
            'date' => '2026-08-05', 'expense_head_id' => $head->id, 'payee' => 'Lift Technician',
            'amount' => 30000, 'treasury_medium' => 'BANK', 'fund_type' => 'SINKING',
        ], $admin1->id);

        \App\Models\BankAccount::create(['bank_name' => 'Test', 'account_no' => '1', 'account_title' => 'GV']);

        $afterFirst = $this->expenseService->approve($expense, $admin1->id);
        $this->assertSame('PENDING', $afterFirst->status);

        $afterSecond = $this->expenseService->approve($afterFirst, $admin2->id);
        $this->assertSame('APPROVED', $afterSecond->status);
    }

    public function test_same_admin_cannot_provide_both_sinking_fund_approvals(): void
    {
        $admin = User::factory()->create();
        $head = ExpenseHead::where('code', 'MAINTENANCE')->first();
        \App\Models\BankAccount::create(['bank_name' => 'Test', 'account_no' => '1', 'account_title' => 'GV']);

        $expense = $this->expenseService->create([
            'date' => '2026-08-05', 'expense_head_id' => $head->id, 'payee' => 'Lift Technician',
            'amount' => 30000, 'treasury_medium' => 'BANK', 'fund_type' => 'SINKING',
        ], $admin->id);

        $expense = $this->expenseService->approve($expense, $admin->id);

        $this->expectException(ValidationException::class);
        $this->expenseService->approve($expense, $admin->id);
    }

    public function test_sinking_fund_expense_over_100000_requires_a_resolution(): void
    {
        $admin1 = User::factory()->create();
        $admin2 = User::factory()->create();
        $head = ExpenseHead::where('code', 'MAINTENANCE')->first();
        \App\Models\BankAccount::create(['bank_name' => 'Test', 'account_no' => '1', 'account_title' => 'GV']);

        $expense = $this->expenseService->create([
            'date' => '2026-08-05', 'expense_head_id' => $head->id, 'payee' => 'Substation repair',
            'amount' => 150000, 'treasury_medium' => 'BANK', 'fund_type' => 'SINKING',
        ], $admin1->id);

        $this->expectException(ValidationException::class);
        $this->expenseService->approve($expense, $admin1->id);
    }

    public function test_monthly_closing_transfers_surplus_to_sinking_fund(): void
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
        $payment = $paymentService->create([
            'flat_id' => $flat->id, 'bill_id' => $bill->id, 'amount' => 3500,
            'payment_date' => '2026-08-05', 'method' => 'CASH',
        ], $user->id);
        $paymentService->approve($payment, $user->id);

        $head = ExpenseHead::where('code', 'UTILITY')->first();
        $expense = $this->expenseService->create([
            'date' => '2026-08-06', 'expense_head_id' => $head->id, 'payee' => 'DESCO',
            'amount' => 1000, 'treasury_medium' => 'CASH',
        ], $user->id);
        $this->expenseService->approve($expense, $user->id);

        $transfer = app(MonthlyClosingService::class)->close(2026, 8, $user->id);

        $this->assertSame('2500.0000', $transfer->amount); // 3500 - 1000
        $this->assertSame('SINKING', $transfer->to_fund);
        $this->assertSame('LOCKED', $period->fresh()->status);
    }

    public function test_closing_an_already_locked_period_is_rejected(): void
    {
        $user = User::factory()->create();
        BillingPeriod::create(['year' => 2026, 'month' => 8, 'status' => 'LOCKED']);

        $this->expectException(ValidationException::class);
        app(MonthlyClosingService::class)->close(2026, 8, $user->id);
    }
}
