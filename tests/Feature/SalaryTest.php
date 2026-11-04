<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\SalaryPayment;
use App\Models\Staff;
use App\Models\User;
use App\Services\TreasuryService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\ExpenseHeadSeeder;
use Database\Seeders\MoneyCorePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StaffPermissionSeeder;
use Database\Seeders\TreasurySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Staff pay: a monthly sheet paid through ordinary expenses, with advances that later salaries take back. */
class SalaryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $checker;

    private Staff $guard;

    private Staff $cleaner;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([ChartOfAccountsSeeder::class, ExpenseHeadSeeder::class, RoleSeeder::class, MoneyCorePermissionSeeder::class, StaffPermissionSeeder::class, TreasurySeeder::class] as $s) {
            $this->seed($s);
        }
        $this->admin = $this->makeAdmin();
        $this->checker = $this->makeAdmin();
        app(TreasuryService::class)->openingBalance('OPERATING', '500000', '0', '2026-01-01', null, $this->admin->id);
        $this->guard = Staff::factory()->create(['name' => 'Karim', 'monthly_salary' => 12000, 'join_date' => '2026-01-01', 'status' => 'ACTIVE']);
        $this->cleaner = Staff::factory()->create(['name' => 'Rahima', 'monthly_salary' => 8000, 'join_date' => '2026-01-01', 'status' => 'ACTIVE']);
    }

    private function makeAdmin(): User
    {
        $u = User::factory()->create(['role' => 'admin']);
        $u->assignRole('admin');

        return $u;
    }

    private function approve(int $expenseId): void
    {
        $this->actingAs($this->checker, 'sanctum')->postJson("/api/expenses/{$expenseId}/approve", ['note' => 'checked'])->assertOk();
    }

    public function test_generating_a_month_makes_one_row_per_active_staff_once(): void
    {
        Staff::factory()->create(['status' => 'INACTIVE', 'monthly_salary' => 5000, 'join_date' => '2026-01-01']);
        Staff::factory()->create(['status' => 'ACTIVE', 'monthly_salary' => 5000, 'join_date' => '2027-06-01']); // not joined yet
        $api = $this->actingAs($this->admin, 'sanctum');

        $api->postJson('/api/salary/generate', ['year' => 2026, 'month' => 10])->assertOk()->assertJsonPath('made', 2);
        $api->postJson('/api/salary/generate', ['year' => 2026, 'month' => 10])->assertOk()->assertJsonPath('made', 0);
        $this->assertSame(2, SalaryPayment::count());
    }

    public function test_the_net_is_worked_out_on_the_server_and_a_paid_row_creates_a_pending_expense(): void
    {
        $api = $this->actingAs($this->admin, 'sanctum');
        $api->postJson('/api/salary/generate', ['year' => 2026, 'month' => 10]);
        $row = SalaryPayment::where('staff_id', $this->guard->id)->first();

        $api->putJson("/api/salary/payments/{$row->id}", ['overtime' => 1500, 'bonus' => 500])->assertOk();
        $this->assertSame('14000.0000', $row->fresh()->net_amount);

        $api->postJson("/api/salary/payments/{$row->id}/pay", ['treasury_medium' => 'CASH'])->assertOk();
        $expense = Expense::find($row->fresh()->expense_id);
        $this->assertSame('PENDING', $expense->status);
        $this->assertSame('14000.0000', (string) $expense->amount);
        $this->assertSame('Karim', $expense->payee);

        // once entered for payment the row is fixed, and cannot be paid twice
        $api->putJson("/api/salary/payments/{$row->id}", ['bonus' => 9999])->assertStatus(422);
        $api->postJson("/api/salary/payments/{$row->id}/pay", ['treasury_medium' => 'CASH'])->assertStatus(422);

        $state = fn () => collect($api->getJson('/api/salary/sheet?year=2026&month=10')->json('rows'))->firstWhere('staff_id', $this->guard->id)['state'];
        $this->assertSame('AWAITING_APPROVAL', $state());
        $this->approve($expense->id);
        $this->assertSame('PAID', $state());
    }

    public function test_an_advance_is_recorded_then_taken_back_from_later_salaries(): void
    {
        $api = $this->actingAs($this->admin, 'sanctum');
        $adv = $api->postJson('/api/salary/advances', ['staff_id' => $this->guard->id, 'amount' => 9000, 'monthly_deduction' => 3000, 'treasury_medium' => 'CASH'])->assertCreated();
        $this->assertSame([], $adv->json('owed')); // not owed until the money has actually left (approval)

        $this->approve(Expense::latest('id')->first()->id);
        $this->assertSame('9000.0000', $api->getJson('/api/salary/advances')->json('owed.0.outstanding'));

        // the next sheet starts with the agreed 3000 taken back
        $api->postJson('/api/salary/generate', ['year' => 2026, 'month' => 11]);
        $row = SalaryPayment::where('staff_id', $this->guard->id)->first();
        $this->assertSame('3000.0000', $row->advance_deduction);
        $this->assertSame('9000.0000', $row->net_amount); // 12000 - 3000

        // cannot take back more than is owed, nor more than the salary
        $api->putJson("/api/salary/payments/{$row->id}", ['advance_deduction' => 9500])->assertStatus(422)->assertJsonValidationErrors('advance_deduction');
        $api->putJson("/api/salary/payments/{$row->id}", ['advance_deduction' => 3000])->assertOk();

        $api->postJson("/api/salary/payments/{$row->id}/pay", ['treasury_medium' => 'CASH'])->assertOk();
        $this->assertSame('6000.0000', $api->getJson('/api/salary/advances')->json('owed.0.outstanding'));
    }

    public function test_pay_all_pays_every_unpaid_row_and_late_ones_are_flagged_after_the_due_day(): void
    {
        $api = $this->actingAs($this->admin, 'sanctum');
        $api->postJson('/api/salary/generate', ['year' => 2026, 'month' => 10]);

        $res = $api->postJson('/api/salary/pay-all', ['year' => 2026, 'month' => 10, 'treasury_medium' => 'CASH'])->assertOk();
        $this->assertSame(2, $res->json('paid'));
        $this->assertSame(2, Expense::count());
        $this->assertSame('20000.0000', $res->json('totals.unpaid')); // awaiting approval, not yet paid

        // an old month nobody generated is late for everyone
        $old = $api->getJson('/api/salary/sheet?year=2026&month=3')->assertOk();
        $this->assertSame(2, $old->json('late_count'));
        $this->assertSame('2026-03-10', $old->json('due_date'));
    }

    public function test_only_staff_managers_can_generate_pay_or_give_advances(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant']);
        $tenant->assignRole('tenant');
        $api = $this->actingAs($tenant, 'sanctum');

        $api->getJson('/api/salary/sheet')->assertForbidden();
        $api->postJson('/api/salary/generate', ['year' => 2026, 'month' => 10])->assertForbidden();
        $api->postJson('/api/salary/advances', ['staff_id' => $this->guard->id, 'amount' => 100, 'treasury_medium' => 'CASH'])->assertForbidden();
    }

    public function test_the_payslip_and_the_sheet_come_out_as_pdf(): void
    {
        $api = $this->actingAs($this->admin, 'sanctum');
        $api->postJson('/api/salary/generate', ['year' => 2026, 'month' => 10]);
        $row = SalaryPayment::first();

        $this->assertStringContainsString('pdf', $api->get("/api/salary/payments/{$row->id}/payslip")->assertOk()->headers->get('content-type'));
        $this->assertStringContainsString('pdf', $api->get('/api/salary/sheet/pdf?year=2026&month=10')->assertOk()->headers->get('content-type'));
    }
}
