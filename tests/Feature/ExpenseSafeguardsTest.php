<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\ExpenseHead;
use App\Models\User;
use App\Services\TreasuryService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\ExpenseHeadSeeder;
use Database\Seeders\MoneyCorePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TreasurySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Two guards that keep an expense logical: the fund must hold the money, and the maker is not the checker. */
class ExpenseSafeguardsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([ChartOfAccountsSeeder::class, ExpenseHeadSeeder::class, RoleSeeder::class, MoneyCorePermissionSeeder::class, TreasurySeeder::class] as $s) {
            $this->seed($s);
        }
        BankAccount::create(['bank_name' => 'B', 'account_no' => '1', 'account_title' => 'GV']);
    }

    private function admin(): User
    {
        $u = User::factory()->create(['role' => 'admin']);
        $u->assignRole('admin');

        return $u;
    }

    private function expense(User $by, string $amount, string $medium = 'CASH'): int
    {
        return $this->actingAs($by, 'sanctum')->postJson('/api/expenses', [
            'date' => '2026-10-20', 'expense_head_id' => ExpenseHead::first()->id, 'payee' => 'X', 'amount' => $amount,
            'treasury_medium' => $medium, 'fund_type' => 'OPERATING',
        ])->assertCreated()->json('id');
    }

    public function test_an_expense_bigger_than_what_the_fund_holds_cannot_be_approved(): void
    {
        $maker = $this->admin();
        $checker = $this->admin();
        app(TreasuryService::class)->openingBalance('OPERATING', '5000', '0', '2026-01-01', null, $maker->id);

        $id = $this->expense($maker, '9000');
        $this->actingAs($checker, 'sanctum')->postJson("/api/expenses/{$id}/approve", ['note' => 'checked'])
            ->assertStatus(422)->assertJsonValidationErrors('amount');
        $this->assertSame('PENDING', \App\Models\Expense::find($id)->status);

        // the bank holds nothing for this fund either
        $bankId = $this->expense($maker, '100', 'BANK');
        $this->actingAs($checker, 'sanctum')->postJson("/api/expenses/{$bankId}/approve", ['note' => 'checked'])->assertStatus(422);

        // an expense the cash covers goes through
        $ok = $this->expense($maker, '4000');
        $this->actingAs($checker, 'sanctum')->postJson("/api/expenses/{$ok}/approve", ['note' => 'checked'])->assertOk()->assertJsonPath('status', 'APPROVED');
    }

    public function test_the_person_who_entered_an_expense_cannot_approve_it_when_someone_else_can(): void
    {
        $maker = $this->admin();
        $this->admin(); // a second approver exists
        app(TreasuryService::class)->openingBalance('OPERATING', '50000', '0', '2026-01-01', null, $maker->id);

        $id = $this->expense($maker, '1000');
        $this->actingAs($maker, 'sanctum')->postJson("/api/expenses/{$id}/approve", ['note' => 'my own'])
            ->assertStatus(422)->assertJsonValidationErrors('approved_by');
    }

    public function test_a_single_admin_can_still_approve_their_own_expense(): void
    {
        $only = $this->admin();
        app(TreasuryService::class)->openingBalance('OPERATING', '50000', '0', '2026-01-01', null, $only->id);

        $id = $this->expense($only, '1000');
        $this->actingAs($only, 'sanctum')->postJson("/api/expenses/{$id}/approve", ['note' => 'only admin'])->assertOk();
    }
}
