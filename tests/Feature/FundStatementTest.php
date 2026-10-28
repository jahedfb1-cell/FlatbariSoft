<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Bill;
use App\Models\BillingPeriod;
use App\Models\CashLedger;
use App\Models\ChartOfAccount;
use App\Models\Flat;
use App\Models\JournalLine;
use App\Models\TreasuryEntry;
use App\Models\User;
use App\Services\ExpenseService;
use App\Services\FundStatementService;
use App\Services\GasPurchaseService;
use App\Services\PaymentService;
use App\Services\TreasuryService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\CommunityHallSeeder;
use Database\Seeders\ExpenseHeadSeeder;
use Database\Seeders\MoneyCorePermissionSeeder;
use Database\Seeders\RoleCatalogSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TreasurySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** The monthly receipts & payments statement of each fund, and the treasury entries that complete it. */
class FundStatementTest extends TestCase
{
    use RefreshDatabase;

    private TreasuryService $treasury;

    private FundStatementService $statements;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([ChartOfAccountsSeeder::class, RoleSeeder::class, MoneyCorePermissionSeeder::class, ExpenseHeadSeeder::class, CommunityHallSeeder::class, RoleCatalogSeeder::class, TreasurySeeder::class] as $seeder) {
            $this->seed($seeder);
        }
        $this->treasury = app(TreasuryService::class);
        $this->statements = app(FundStatementService::class);
        $this->admin = $this->user('admin');
        BankAccount::create(['bank_name' => 'Test Bank', 'account_title' => 'GV', 'account_no' => '1', 'opening_balance' => 0, 'current_balance' => 0]);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        $user->assignRole($role);

        return $user;
    }

    private function opening(string $fund, string $cash, string $bank, string $date = '2026-09-01'): void
    {
        $this->treasury->openingBalance($fund, $cash, $bank, $date, null, $this->admin->id);
    }

    private function sept(string $fund = 'OPERATING'): array
    {
        return $this->statements->statement($fund, 2026, 9);
    }

    /** A paid expense in a given fund and on a given day. */
    private function spend(string $fund, string $amount, string $date, string $head = 'UTILITY', string $payee = 'Vendor'): void
    {
        $svc = app(ExpenseService::class);
        $e = $svc->create([
            'date' => $date, 'expense_head_id' => \App\Models\ExpenseHead::where('code', $head)->value('id'), 'payee' => $payee,
            'amount' => $amount, 'description' => 'Bill', 'treasury_medium' => 'CASH', 'fund_type' => $fund,
        ], $this->admin->id);
        $svc->approve($e, $this->user('admin')->id);
    }

    // ---- opening balance and the basic shape

    public function test_an_opening_balance_posts_cash_and_bank_with_a_balanced_journal(): void
    {
        $this->opening('OPERATING', '462450', '7800');

        $this->assertSame('462450.0000', $this->treasury->cashBalance('OPERATING'));
        $this->assertSame('7800.0000', $this->treasury->bankBalance('OPERATING'));
        $entry = TreasuryEntry::where('medium', 'CASH')->first();
        $lines = JournalLine::where('journal_entry_id', $entry->journal_entry_id)->get();
        $this->assertEquals($lines->sum('debit'), $lines->sum('credit'));
        $this->assertNotNull($lines->firstWhere('account_id', ChartOfAccount::where('code', '3900')->first()->id));
    }

    public function test_the_statement_starts_from_the_opening_balance_and_both_sides_always_match(): void
    {
        $this->opening('OPERATING', '462450', '7800');
        $this->spend('OPERATING', '16000', '2026-09-08', 'STAFF_SALARY', 'Manager');
        $this->spend('OPERATING', '32017', '2026-09-23', 'UTILITY', 'DESCO');

        $s = $this->sept();

        $this->assertSame('462450.0000', $s['opening']['cash']);
        $this->assertSame('7800.0000', $s['opening']['bank']);
        $this->assertSame('48017.0000', $s['totals']['payments']);
        $this->assertSame('0.0000', $s['totals']['receipts']);   // the opening balance is not a receipt
        $this->assertSame('414433.0000', $s['closing']['cash']);
        $this->assertSame($s['left_total'], $s['right_total']);
        $this->assertSame('470250.0000', $s['left_total']);
    }

    public function test_payments_are_grouped_by_head_with_the_biggest_first(): void
    {
        $this->opening('OPERATING', '500000', '0');
        $this->spend('OPERATING', '16000', '2026-09-08', 'STAFF_SALARY');
        $this->spend('OPERATING', '10000', '2026-09-08', 'STAFF_SALARY');
        $this->spend('OPERATING', '32017', '2026-09-23', 'UTILITY');

        $heads = $this->sept()['payments_by_head'];

        $this->assertSame(['EXP_UTILITY', 'EXP_STAFF_SALARY'], array_column($heads, 'code'));
        $this->assertSame('26000.0000', $heads[1]['amount']);
    }

    public function test_entries_land_in_the_month_of_their_own_date(): void
    {
        $this->opening('OPERATING', '100000', '0', '2026-08-01');
        $this->spend('OPERATING', '1000', '2026-08-31');   // before the month: part of the opening
        $this->spend('OPERATING', '2000', '2026-09-15');
        $this->spend('OPERATING', '4000', '2026-10-01');   // after the month: not in September

        $s = $this->sept();

        $this->assertSame('99000.0000', $s['opening']['cash']);
        $this->assertSame('2000.0000', $s['totals']['payments']);
        $this->assertSame('97000.0000', $s['closing']['cash']);
        $this->assertSame('93000.0000', $this->statements->statement('OPERATING', 2026, 10)['closing']['cash']);
    }

    public function test_an_opening_balance_dated_inside_the_month_is_still_the_opening_not_a_receipt(): void
    {
        $this->opening('OPERATING', '50000', '0', '2026-09-01');

        $s = $this->sept();

        $this->assertSame('50000.0000', $s['opening']['cash']);
        $this->assertSame([], $s['receipts']);
    }

    // ---- service charge and gas are separate funds

    public function test_one_receipt_with_service_charge_and_gas_is_split_between_the_two_funds(): void
    {
        $flat = Flat::factory()->create();
        $period = BillingPeriod::create(['year' => 2026, 'month' => 9, 'status' => 'GENERATED']);
        $bill = Bill::create([
            'billing_period_id' => $period->id, 'flat_id' => $flat->id, 'service_charge' => 3000, 'gas_amount' => 1000,
            'previous_due' => 0, 'total_payable' => 4000, 'paid_amount' => 0, 'due_date' => '2026-09-10', 'status' => 'UNPAID',
        ]);
        $svc = app(PaymentService::class);
        $p = $svc->create(['flat_id' => $flat->id, 'bill_id' => $bill->id, 'amount' => 4000, 'payment_date' => '2026-09-05', 'method' => 'CASH'], $this->admin->id);
        $svc->approve($p, $this->user('admin')->id);

        $service = $this->sept('OPERATING');
        $gas = $this->sept('GAS');

        $this->assertSame('3000.0000', $service['totals']['receipts']);
        $this->assertSame('SERVICE_CHARGE', $service['receipts_by_head'][0]['code']);
        $this->assertSame('1000.0000', $gas['totals']['receipts']);
        $this->assertSame('GAS_BILL', $gas['receipts_by_head'][0]['code']);
        // physical cash is still the full amount
        $this->assertSame('4000.0000', CashLedger::orderByDesc('id')->first()->balance_after);
    }

    public function test_a_service_charge_only_receipt_still_posts_one_cash_row(): void
    {
        $flat = Flat::factory()->create();
        $svc = app(PaymentService::class);
        $p = $svc->create(['flat_id' => $flat->id, 'amount' => 500, 'payment_date' => '2026-09-05', 'method' => 'CASH'], $this->admin->id);
        $svc->approve($p, $this->user('admin')->id);

        $this->assertSame(1, CashLedger::count());
        $this->assertSame('500.0000', $this->sept('OPERATING')['totals']['receipts']);
        $this->assertSame('0.0000', $this->sept('GAS')['totals']['receipts']);
    }

    public function test_a_gas_purchase_appears_in_the_gas_statement_with_its_quantity(): void
    {
        $this->opening('GAS', '200000', '0');
        $p = app(GasPurchaseService::class)->record(['date' => '2026-09-03', 'supplier_name' => 'Bashundhara', 'quantity_kg' => 405, 'unit_price' => 141.11, 'transport_cost' => 0, 'treasury_medium' => 'CASH'], $this->admin->id);
        app(ExpenseService::class)->approve($p->expense, $this->user('admin')->id);

        $gas = $this->sept('GAS');

        $this->assertSame('57149.5500', $gas['totals']['payments']); // 405 kg x 141.11
        $this->assertStringContainsString('405 kg @ 141.11', $gas['payments'][0]['particulars']);
        $this->assertSame('EXP_GAS_PURCHASE', $gas['payments_by_head'][0]['code']);
        $this->assertSame('0.0000', $this->sept('OPERATING')['totals']['payments']); // not in the service charge account
    }

    // ---- bank

    public function test_cash_deposited_in_the_bank_is_not_a_receipt_or_a_payment(): void
    {
        $this->opening('OPERATING', '100000', '0');
        $this->treasury->depositToBank('OPERATING', '30000', '2026-09-10', null, $this->admin->id);

        $s = $this->sept();

        $this->assertSame('70000.0000', $s['closing']['cash']);
        $this->assertSame('30000.0000', $s['closing']['bank']);
        $this->assertSame([], $s['receipts']);
        $this->assertSame([], $s['payments']);
        $this->assertSame('30000.0000', $s['bank_note']['cash_deposited']);
        $this->assertSame($s['left_total'], $s['right_total']);
    }

    public function test_withdrawing_from_the_bank_and_bank_profit(): void
    {
        $this->opening('OPERATING', '0', '15300');
        $this->treasury->withdrawFromBank('OPERATING', '15000', '2026-09-29', null, $this->admin->id);
        $this->treasury->bankProfit('OPERATING', '500', '2026-09-30', 'half-yearly', $this->admin->id);

        $s = $this->sept();

        $this->assertSame('15000.0000', $s['closing']['cash']);
        $this->assertSame('800.0000', $s['closing']['bank']);                     // 15,300 - 15,000 + 500
        $this->assertSame('BANK_PROFIT', $s['receipts_by_head'][0]['code']);
        $this->assertSame('500.0000', $s['totals']['receipts']);
        $this->assertSame('15000.0000', $s['bank_note']['withdrawn']);
        $this->assertSame('500.0000', $s['bank_note']['profit']);
        $this->assertSame($s['left_total'], $s['right_total']);
    }

    public function test_the_bank_note_reproduces_the_committees_september_example(): void
    {
        // the sheet: bank 7,800 + received at bank 7,000 + profit 500 = 15,300; withdrawn 15,000; balance 300
        $this->opening('OPERATING', '0', '7800');
        $flat = Flat::factory()->create();
        $svc = app(PaymentService::class);
        $p = $svc->create(['flat_id' => $flat->id, 'amount' => 7000, 'payment_date' => '2026-09-10', 'method' => 'BANK'], $this->admin->id);
        $svc->approve($p, $this->user('admin')->id);
        $this->treasury->bankProfit('OPERATING', '500', '2026-09-30', null, $this->admin->id);
        $this->treasury->withdrawFromBank('OPERATING', '15000', '2026-09-29', null, $this->admin->id);

        $n = $this->sept()['bank_note'];

        $this->assertSame('7800.0000', $n['opening']);
        $this->assertSame('7000.0000', $n['other_receipts']);
        $this->assertSame('500.0000', $n['profit']);
        $this->assertSame('15000.0000', $n['withdrawn']);
        $this->assertSame('300.0000', $n['closing']);
        $this->assertSame('15000.0000', $this->sept()['closing']['cash']);
    }

    // ---- a fund cannot spend what it does not hold

    public function test_a_fund_cannot_deposit_withdraw_or_lend_more_than_it_holds(): void
    {
        $this->opening('OPERATING', '1000', '500');

        foreach ([
            fn () => $this->treasury->depositToBank('OPERATING', '1000.01', '2026-09-02', null, $this->admin->id),
            fn () => $this->treasury->withdrawFromBank('OPERATING', '500.01', '2026-09-02', null, $this->admin->id),
            fn () => $this->treasury->lend('OPERATING', 'GAS', 'CASH', '1000.01', '2026-09-02', null, $this->admin->id),
            fn () => $this->treasury->lend('OPERATING', 'GAS', 'BANK', '500.01', '2026-09-02', null, $this->admin->id),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('must be refused');
            } catch (ValidationException) {
            }
        }
        $this->assertSame('1000.0000', $this->treasury->cashBalance('OPERATING')); // nothing leaked out
    }

    // ---- loans between funds

    public function test_a_loan_is_a_payment_for_the_lender_and_a_receipt_for_the_borrower(): void
    {
        $this->opening('OPERATING', '500000', '0');
        $this->treasury->lend('OPERATING', 'GAS', 'CASH', '30150', '2026-09-20', null, $this->admin->id);

        $service = $this->sept('OPERATING');
        $gas = $this->sept('GAS');

        $this->assertSame('30150.0000', $service['totals']['payments']);
        $this->assertSame('LOAN_GIVEN', $service['payments_by_head'][0]['code']);
        $this->assertStringContainsString('Loan given to LPG Gas Bill', $service['payments'][0]['particulars']);
        $this->assertSame('30150.0000', $gas['totals']['receipts']);
        $this->assertSame('LOAN_TAKEN', $gas['receipts_by_head'][0]['code']);
        $this->assertStringContainsString('Loan taken from Service Charges', $gas['receipts'][0]['particulars']);
        $this->assertSame('30150.0000', $gas['closing']['cash']);
        // together the cash in the building did not change
        $this->assertSame('500000.0000', bcadd($service['closing']['cash'], $gas['closing']['cash'], 4));
        $this->assertSame($service['left_total'], $service['right_total']);
        $this->assertSame($gas['left_total'], $gas['right_total']);
    }

    public function test_the_loan_position_and_repayments(): void
    {
        $this->opening('OPERATING', '500000', '0');
        $this->treasury->lend('OPERATING', 'GAS', 'CASH', '30150', '2026-09-20', null, $this->admin->id);

        $this->assertSame('30150.0000', $this->treasury->owed('GAS', 'OPERATING'));
        $this->assertSame([['lender' => 'OPERATING', 'borrower' => 'GAS', 'owed' => '30150.0000']], $this->treasury->loanPositions());

        $this->treasury->repay('GAS', 'OPERATING', 'CASH', '24025', '2026-10-01', null, $this->admin->id);
        $this->assertSame('6125.0000', $this->treasury->owed('GAS', 'OPERATING'));

        try {
            $this->treasury->repay('GAS', 'OPERATING', 'CASH', '6125.01', '2026-10-02', null, $this->admin->id);
            $this->fail('repaying more than owed must be refused');
        } catch (ValidationException) {
        }

        $oct = $this->statements->statement('GAS', 2026, 10);
        $this->assertSame('LOAN_REPAID_OUT', $oct['payments_by_head'][0]['code']);
        $this->assertSame('6125.0000', $oct['loans'][0]['owed']);
    }

    public function test_a_fund_cannot_lend_to_itself(): void
    {
        $this->opening('OPERATING', '1000', '0');

        $this->expectException(ValidationException::class);
        $this->treasury->lend('OPERATING', 'OPERATING', 'CASH', '10', '2026-09-02', null, $this->admin->id);
    }

    // ---- reversals

    public function test_reversing_a_loan_undoes_both_sides_and_the_position(): void
    {
        $this->opening('OPERATING', '1000', '0');
        $loan = $this->treasury->lend('OPERATING', 'GAS', 'CASH', '400', '2026-09-02', null, $this->admin->id);

        $this->treasury->reverse($loan, 'wrong fund', $this->admin->id);

        $this->assertSame('1000.0000', $this->treasury->cashBalance('OPERATING'));
        $this->assertSame('0.0000', $this->treasury->cashBalance('GAS'));
        $this->assertSame('0.0000', $this->treasury->owed('GAS', 'OPERATING'));
        $this->assertSame([], $this->treasury->loanPositions());
        $this->assertDatabaseHas('treasury_entries', ['id' => $loan->id, 'amount' => 400]); // the original stays
    }

    public function test_a_loan_that_was_partly_repaid_cannot_be_reversed_first(): void
    {
        $this->opening('OPERATING', '1000', '0');
        $loan = $this->treasury->lend('OPERATING', 'GAS', 'CASH', '400', '2026-09-02', null, $this->admin->id);
        $this->treasury->repay('GAS', 'OPERATING', 'CASH', '100', '2026-09-03', null, $this->admin->id);

        $this->expectException(ValidationException::class);
        $this->treasury->reverse($loan, 'x', $this->admin->id);
    }

    public function test_an_entry_cannot_be_reversed_twice(): void
    {
        $this->opening('OPERATING', '1000', '0');
        $e = $this->treasury->depositToBank('OPERATING', '100', '2026-09-02', null, $this->admin->id);
        $rev = $this->treasury->reverse($e, 'x', $this->admin->id);

        try {
            $this->treasury->reverse($e, 'again', $this->admin->id);
            $this->fail('second reversal must be refused');
        } catch (ValidationException) {
        }
        $this->expectException(ValidationException::class);
        $this->treasury->reverse($rev, 'undo the undo', $this->admin->id);
    }

    public function test_reversing_an_opening_balance_removes_it_from_the_opening(): void
    {
        $this->opening('OPERATING', '1000', '0');
        $this->treasury->reverse(TreasuryEntry::first(), 'entered twice', $this->admin->id);

        $this->assertSame('0.0000', $this->sept()['opening']['cash']);
    }

    // ---- the summary and the books of account

    public function test_the_summary_ties_the_four_funds_to_the_cash_and_bank_actually_held(): void
    {
        $this->opening('OPERATING', '500000', '10000');
        $this->opening('GAS', '20000', '0');
        $this->treasury->lend('OPERATING', 'GAS', 'CASH', '30000', '2026-09-20', null, $this->admin->id);
        $this->spend('GAS', '5000', '2026-09-21');

        $sum = $this->statements->summary(2026, 9);

        $held = bcadd((string) CashLedger::orderByDesc('id')->value('balance_after'), (string) BankAccount::first()->fresh()->current_balance, 4);
        $this->assertSame($held, $sum['total']['closing']);
        $this->assertSame(4, count($sum['funds']));
        $this->assertSame('OPERATING', $sum['loans'][0]['lender']);
        $this->assertSame('30000.0000', $sum['loans'][0]['owed']);
    }

    public function test_every_treasury_entry_posts_a_balanced_journal(): void
    {
        $this->opening('OPERATING', '100000', '50000');
        $this->treasury->depositToBank('OPERATING', '1000', '2026-09-02', null, $this->admin->id);
        $this->treasury->withdrawFromBank('OPERATING', '500', '2026-09-03', null, $this->admin->id);
        $this->treasury->bankProfit('OPERATING', '200', '2026-09-04', null, $this->admin->id);
        $this->treasury->lend('OPERATING', 'GAS', 'CASH', '3000', '2026-09-05', null, $this->admin->id);
        $this->treasury->repay('GAS', 'OPERATING', 'CASH', '1000', '2026-09-06', null, $this->admin->id);

        foreach (TreasuryEntry::all() as $entry) {
            $lines = JournalLine::where('journal_entry_id', $entry->journal_entry_id)->get();
            $this->assertCount(2, $lines, $entry->kind);
            $this->assertEquals($lines->sum('debit'), $lines->sum('credit'), $entry->kind);
        }
    }

    // ---- HTTP and permissions

    public function test_http_statement_pdf_excel_and_summary(): void
    {
        $this->opening('OPERATING', '100000', '0');
        $this->spend('OPERATING', '1000', '2026-09-08');
        $this->actingAs($this->admin, 'sanctum');

        $res = $this->getJson('/api/treasury/statement?fund=OPERATING&year=2026&month=9')->assertOk();
        $this->assertSame($res->json('left_total'), $res->json('right_total'));
        $this->getJson('/api/treasury/summary?year=2026&month=9')->assertOk()->assertJsonCount(4, 'funds');
        $this->assertStringStartsWith('%PDF', $this->get('/api/treasury/statement/pdf?fund=GAS&year=2026&month=9')->assertOk()->getContent());
        $this->get('/api/treasury/statement/excel?fund=OPERATING&year=2026&month=9')->assertOk();
        $this->getJson('/api/treasury/statement?fund=NOPE&year=2026&month=9')->assertStatus(422);
        $this->getJson('/api/treasury/statement?year=2026&month=13')->assertStatus(422);
    }

    public function test_http_entries_need_treasury_manage_and_reading_needs_accounting_view(): void
    {
        $body = ['fund' => 'OPERATING', 'cash' => 1000, 'bank' => 0, 'date' => '2026-09-01'];
        $treasurer = $this->user('committee_treasurer');
        $member = $this->user('committee_member');
        $manager = $this->user('manager');

        $this->actingAs($member, 'sanctum')->getJson('/api/treasury/statement?fund=OPERATING&year=2026&month=9')->assertOk();
        $this->actingAs($member, 'sanctum')->postJson('/api/treasury/opening-balance', $body)->assertForbidden();
        $this->actingAs($manager, 'sanctum')->getJson('/api/treasury/summary?year=2026&month=9')->assertForbidden(); // no accounting.view
        $this->actingAs($treasurer, 'sanctum')->postJson('/api/treasury/opening-balance', $body)->assertCreated();

        $this->actingAs($treasurer, 'sanctum')->postJson('/api/treasury/bank-deposit', ['fund' => 'OPERATING', 'amount' => 400, 'date' => '2026-09-02'])->assertCreated();
        $this->actingAs($treasurer, 'sanctum')->postJson('/api/treasury/bank-deposit', ['fund' => 'OPERATING', 'amount' => 999999, 'date' => '2026-09-02'])->assertStatus(422);
        $this->actingAs($treasurer, 'sanctum')->postJson('/api/treasury/loans', ['from_fund' => 'OPERATING', 'to_fund' => 'OPERATING', 'medium' => 'CASH', 'amount' => 1, 'date' => '2026-09-02'])->assertStatus(422);
        $this->actingAs($treasurer, 'sanctum')->postJson('/api/treasury/loans', ['from_fund' => 'OPERATING', 'to_fund' => 'GAS', 'medium' => 'CASH', 'amount' => 100, 'date' => '2026-09-02'])->assertCreated();
        $this->actingAs($treasurer, 'sanctum')->postJson('/api/treasury/loan-repayments', ['from_fund' => 'GAS', 'to_fund' => 'OPERATING', 'medium' => 'CASH', 'amount' => 40, 'date' => '2026-09-03'])->assertCreated();

        $list = $this->actingAs($treasurer, 'sanctum')->getJson('/api/treasury/entries')->assertOk();
        $this->assertCount(4, $list->json()); // opening (cash only), deposit, loan, repayment
        $id = collect($list->json())->firstWhere('kind', 'BANK_PROFIT')['id'] ?? collect($list->json())->firstWhere('kind', 'DEPOSIT_TO_BANK')['id'];
        $this->actingAs($treasurer, 'sanctum')->postJson("/api/treasury/entries/{$id}/reverse", ['reason' => 'test'])->assertOk();
    }
}
