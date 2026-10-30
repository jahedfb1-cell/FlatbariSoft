<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\ExpenseHeadSeeder;
use Database\Seeders\OperationsPermissionSeeder;
use Database\Seeders\RoleCatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Last year's comparison, the twelve months, copying a budget forward, and the PDF / Excel copies. */
class BudgetCompleteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ExpenseHead $head;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(ExpenseHeadSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RoleCatalogSeeder::class);
        $this->seed(OperationsPermissionSeeder::class);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->assignRole('admin');
        $this->head = ExpenseHead::orderBy('code')->first();
    }

    private function spend(string $date, string $amount, string $status = 'APPROVED'): void
    {
        Expense::create([
            'date' => $date, 'expense_head_id' => $this->head->id, 'payee' => 'x', 'amount' => $amount, 'description' => 'x',
            'treasury_medium' => 'CASH', 'fund_type' => 'OPERATING', 'created_by' => $this->admin->id, 'status' => $status,
        ]);
    }

    private function row(array $report): array
    {
        return collect($report['rows'])->firstWhere('expense_head_id', $this->head->id);
    }

    public function test_last_years_figures_and_the_change_are_in_the_report(): void
    {
        Budget::create(['fiscal_year' => 2025, 'expense_head_id' => $this->head->id, 'amount' => 80000]);
        $this->spend('2025-09-10', '1000');   // FY 2025-26
        $this->spend('2026-08-10', '1500');   // FY 2026-27

        $r = $this->actingAs($this->admin, 'sanctum')->getJson('/api/budget?fiscal_year=2026')->assertOk()->json();
        $row = $this->row($r);

        $this->assertSame('2025-26', $r['last_label']);
        $this->assertSame('80000.0000', $row['last_budget']);
        $this->assertSame('1000.0000', $row['last_spent']);
        $this->assertEquals(50.0, $row['spent_change_percent']);   // 1000 -> 1500
        $this->assertSame('1000.0000', $r['totals']['last_spent']);
    }

    public function test_months_run_july_to_june_and_only_count_approved_spending(): void
    {
        $this->spend('2026-07-05', '100');
        $this->spend('2026-07-20', '50');
        $this->spend('2027-06-30', '300');
        $this->spend('2026-12-01', '999', 'PENDING');

        $r = $this->actingAs($this->admin, 'sanctum')->getJson('/api/budget?fiscal_year=2026')->json();

        $this->assertCount(12, $r['months']);
        $this->assertSame([7, 2026], [$r['months'][0]['month'], $r['months'][0]['year']]);
        $this->assertSame([6, 2027], [$r['months'][11]['month'], $r['months'][11]['year']]);
        $this->assertSame('150.0000', $r['months'][0]['spent']);
        $this->assertSame('300.0000', $r['months'][11]['spent']);
        $this->assertSame('0.0000', $r['months'][5]['spent']);   // December: the pending one is not counted

        $row = $this->row($r);
        $this->assertSame('150.0000', $row['monthly'][0]);
        $this->assertSame('450.0000', $row['spent']);
    }

    public function test_a_budget_is_copied_forward_with_an_increase_and_never_wipes_typed_amounts(): void
    {
        $other = ExpenseHead::orderBy('code')->skip(1)->first();
        Budget::create(['fiscal_year' => 2025, 'expense_head_id' => $this->head->id, 'amount' => 100000]);
        Budget::create(['fiscal_year' => 2025, 'expense_head_id' => $other->id, 'amount' => 40000]);
        Budget::create(['fiscal_year' => 2026, 'expense_head_id' => $other->id, 'amount' => 55555]);   // already typed in

        $api = $this->actingAs($this->admin, 'sanctum');
        $res = $api->postJson('/api/budget/copy', ['from_year' => 2025, 'to_year' => 2026, 'increase_percent' => 10])->assertOk();

        $this->assertSame(1, $res->json('copied'));
        $this->assertSame('110000.0000', Budget::where('fiscal_year', 2026)->where('expense_head_id', $this->head->id)->value('amount'));
        $this->assertSame('55555.0000', Budget::where('fiscal_year', 2026)->where('expense_head_id', $other->id)->value('amount'));

        $api->postJson('/api/budget/copy', ['from_year' => 2025, 'to_year' => 2026, 'overwrite' => true])->assertOk()->assertJsonPath('copied', 2);
        $this->assertSame('40000.0000', Budget::where('fiscal_year', 2026)->where('expense_head_id', $other->id)->value('amount'));
    }

    public function test_copy_rejects_the_same_year_and_an_empty_source(): void
    {
        $api = $this->actingAs($this->admin, 'sanctum');
        $api->postJson('/api/budget/copy', ['from_year' => 2026, 'to_year' => 2026])->assertStatus(422)->assertJsonValidationErrors('to_year');
        $api->postJson('/api/budget/copy', ['from_year' => 2020, 'to_year' => 2026])->assertStatus(422)->assertJsonValidationErrors('from_year');
        $api->postJson('/api/budget/copy', ['from_year' => 2025, 'to_year' => 2026, 'increase_percent' => 9999])->assertStatus(422);
    }

    public function test_pdf_and_excel_copies_come_out(): void
    {
        Budget::create(['fiscal_year' => 2026, 'expense_head_id' => $this->head->id, 'amount' => 5000]);
        $this->spend('2026-09-01', '1200');
        $api = $this->actingAs($this->admin, 'sanctum');

        $this->assertStringStartsWith('%PDF', $api->get('/api/budget/pdf?fiscal_year=2026')->assertOk()->getContent());
        $xlsx = $api->get('/api/budget/excel?fiscal_year=2026')->assertOk();
        $this->assertStringContainsString('spreadsheetml', $xlsx->headers->get('content-type'));
    }

    public function test_copy_needs_the_manage_permission_but_the_copies_only_the_view_permission(): void
    {
        $member = User::factory()->create(['role' => 'committee_member']);
        $member->assignRole('committee_member');
        $api = $this->actingAs($member, 'sanctum');

        $api->postJson('/api/budget/copy', ['from_year' => 2025, 'to_year' => 2026])->assertForbidden();
        $api->get('/api/budget/pdf?fiscal_year=2026')->assertOk();

        $tenant = User::factory()->create(['role' => 'tenant']);
        $this->actingAs($tenant, 'sanctum')->get('/api/budget/pdf')->assertForbidden();
    }
}
