<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\User;
use App\Services\BudgetService;
use Carbon\Carbon;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\ExpenseHeadSeeder;
use Database\Seeders\OperationsPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Budget vs actual over the July-June fiscal year. */
class BudgetTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(ExpenseHeadSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(\Database\Seeders\RoleCatalogSeeder::class);
        $this->seed(OperationsPermissionSeeder::class);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->assignRole('admin');
    }

    private function spend(ExpenseHead $head, string $date, string $amount, string $status = 'APPROVED'): void
    {
        Expense::create([
            'date' => $date, 'expense_head_id' => $head->id, 'payee' => 'x', 'amount' => $amount, 'description' => 'x',
            'treasury_medium' => 'CASH', 'fund_type' => 'OPERATING', 'created_by' => $this->admin->id, 'status' => $status,
        ]);
    }

    public function test_fiscal_year_runs_july_to_june(): void
    {
        $this->assertSame(2026, BudgetService::fiscalYearOf(Carbon::parse('2026-07-01')));
        $this->assertSame(2026, BudgetService::fiscalYearOf(Carbon::parse('2027-06-30')));
        $this->assertSame(2025, BudgetService::fiscalYearOf(Carbon::parse('2026-06-30')));
    }

    public function test_actual_spending_counts_only_approved_expenses_inside_the_year(): void
    {
        $head = ExpenseHead::first();
        $this->spend($head, '2026-08-10', '1000');
        $this->spend($head, '2027-06-30', '500');
        $this->spend($head, '2026-06-30', '9999');            // previous fiscal year
        $this->spend($head, '2026-09-01', '7777', 'PENDING'); // not approved

        $api = $this->actingAs($this->admin, 'sanctum');
        $api->putJson('/api/budget', ['fiscal_year' => 2026, 'items' => [['expense_head_id' => $head->id, 'amount' => '1200']]])->assertOk();

        $res = $api->getJson('/api/budget?fiscal_year=2026')->assertOk();
        $row = collect($res->json('rows'))->firstWhere('expense_head_id', $head->id);
        $this->assertSame('1500.0000', $row['spent']);
        $this->assertSame('1200.0000', $row['budget']);
        $this->assertSame('-300.0000', $row['remaining']);
        $this->assertTrue($row['over']);
        $this->assertEquals(125.0, $row['percent']);
        $this->assertSame('1200.0000', $res->json('totals.budget'));
        $this->assertSame('2026-27', $res->json('label'));
    }

    public function test_saving_twice_updates_rather_than_duplicates_and_validates(): void
    {
        $head = ExpenseHead::first();
        $api = $this->actingAs($this->admin, 'sanctum');
        $api->putJson('/api/budget', ['fiscal_year' => 2026, 'items' => [['expense_head_id' => $head->id, 'amount' => '100']]])->assertOk();
        $api->putJson('/api/budget', ['fiscal_year' => 2026, 'items' => [['expense_head_id' => $head->id, 'amount' => '250']]])->assertOk();
        $this->assertDatabaseCount('budgets', 1);
        $this->assertDatabaseHas('budgets', ['expense_head_id' => $head->id, 'amount' => 250]);

        $api->putJson('/api/budget', ['fiscal_year' => 2026, 'items' => [['expense_head_id' => 9999, 'amount' => '1']]])->assertStatus(422);
        $api->putJson('/api/budget', ['fiscal_year' => 2026, 'items' => [['expense_head_id' => $head->id, 'amount' => '-5']]])->assertStatus(422);
    }

    public function test_viewers_can_read_but_not_write(): void
    {
        $member = User::factory()->create(['role' => 'committee_member']);
        $member->assignRole('committee_member');
        $this->actingAs($member, 'sanctum')->getJson('/api/budget')->assertOk();
        $this->actingAs($member, 'sanctum')->putJson('/api/budget', ['fiscal_year' => 2026, 'items' => [['expense_head_id' => 1, 'amount' => 1]]])->assertForbidden();
    }
}
