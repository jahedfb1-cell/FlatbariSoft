<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\User;
use App\Services\AccountingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Phase11AccountingEngineTest extends TestCase
{
    use RefreshDatabase;

    private AccountingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AccountingService::class);
        $this->seed(\Database\Seeders\CollectionHeadSeeder::class);
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);
    }

    public function test_collection_heads_are_seeded_with_correct_payer_types(): void
    {
        $this->assertDatabaseHas('collection_heads', ['code' => 'SERVICE_CHARGE', 'payer_type' => 'OWNER']);
        $this->assertDatabaseHas('collection_heads', ['code' => 'GAS_BILL', 'payer_type' => 'OCCUPANT']);
    }

    public function test_chart_of_accounts_has_all_five_account_types(): void
    {
        $types = ChartOfAccount::pluck('account_type')->unique()->sort()->values()->all();
        $this->assertSame(['ASSET', 'EXPENSE', 'FUND', 'INCOME', 'LIABILITY'], $types);
    }

    public function test_fiscal_year_follows_july_to_june(): void
    {
        $this->assertSame('2026-2027', $this->service->fiscalYearFor(Carbon::parse('2026-08-15')));
        $this->assertSame('2025-2026', $this->service->fiscalYearFor(Carbon::parse('2026-03-15')));
        $this->assertSame('2026-2027', $this->service->fiscalYearFor(Carbon::parse('2026-07-01')));
        $this->assertSame('2025-2026', $this->service->fiscalYearFor(Carbon::parse('2026-06-30')));
    }

    public function test_balanced_journal_entry_posts_successfully(): void
    {
        $user = User::factory()->create();
        $bank = ChartOfAccount::where('code', '1100')->first();
        $income = ChartOfAccount::where('code', '4100')->first();

        $entry = $this->service->postJournal(
            [
                ['account_id' => $bank->id, 'debit' => 5000],
                ['account_id' => $income->id, 'credit' => 5000],
            ],
            'Test service charge collection',
            '2026-08-15',
            $user->id,
        );

        $this->assertSame('2026-2027', $entry->fiscal_year);
        $this->assertCount(2, $entry->lines);
        $this->assertDatabaseHas('journal_entries', ['id' => $entry->id, 'status' => 'POSTED']);
    }

    public function test_unbalanced_journal_entry_is_rejected(): void
    {
        $user = User::factory()->create();
        $bank = ChartOfAccount::where('code', '1100')->first();
        $income = ChartOfAccount::where('code', '4100')->first();

        $this->expectException(ValidationException::class);

        $this->service->postJournal(
            [
                ['account_id' => $bank->id, 'debit' => 5000],
                ['account_id' => $income->id, 'credit' => 4000], // deliberately wrong
            ],
            'Broken entry',
            '2026-08-15',
            $user->id,
        );
    }

    public function test_bank_transaction_updates_running_balance(): void
    {
        $user = User::factory()->create();
        $bank = BankAccount::create([
            'bank_name' => 'Test Bank', 'account_no' => '123', 'account_title' => 'Green Valley',
            'opening_balance' => 10000, 'current_balance' => 10000,
        ]);

        $txn1 = $this->service->postBankTransaction($bank, [
            'txn_date' => '2026-08-01', 'particulars' => 'Deposit', 'debit' => 5000,
            'fund_type' => 'OPERATING', 'created_by' => $user->id,
        ]);
        $this->assertSame('15000.0000', $txn1->balance_after);

        $txn2 = $this->service->postBankTransaction($bank, [
            'txn_date' => '2026-08-02', 'particulars' => 'Withdrawal', 'credit' => 3000,
            'fund_type' => 'OPERATING', 'created_by' => $user->id,
        ]);
        $this->assertSame('12000.0000', $txn2->balance_after);
        $this->assertSame('12000.0000', $bank->fresh()->current_balance);
    }

    public function test_cash_ledger_running_balance_chains_across_entries(): void
    {
        $user = User::factory()->create();

        $first = $this->service->postCashTransaction([
            'txn_date' => '2026-08-01', 'particulars' => 'Collection', 'in_amount' => 2000,
            'fund_type' => 'OPERATING', 'created_by' => $user->id,
        ]);
        $this->assertSame('2000.0000', $first->balance_after);

        $second = $this->service->postCashTransaction([
            'txn_date' => '2026-08-02', 'particulars' => 'Expense', 'out_amount' => 500,
            'fund_type' => 'OPERATING', 'created_by' => $user->id,
        ]);
        $this->assertSame('1500.0000', $second->balance_after);
    }
}
