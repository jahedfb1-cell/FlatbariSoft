<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ConstructionMaterialPurchase;
use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\GasPurchase;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\ExpenseHeadSeeder;
use Database\Seeders\MoneyCorePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Material purchases and plain expenses can be corrected; an approver can reject a pending expense with a reason. */
class ExpenseCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $maker;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([ChartOfAccountsSeeder::class, ExpenseHeadSeeder::class, RoleSeeder::class, MoneyCorePermissionSeeder::class, \Database\Seeders\GasPermissionSeeder::class] as $s) {
            if (class_exists($s)) {
                $this->seed($s);
            }
        }
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->assignRole('admin');
        $this->maker = User::factory()->create(['role' => 'manager']);
        $this->maker->assignRole('manager'); // may enter expenses, may not approve them
    }

    private function headId(): int
    {
        return ExpenseHead::where('code', 'MAINTENANCE')->value('id');
    }

    private function expenseBody(array $over = []): array
    {
        return $over + ['date' => now()->toDateString(), 'expense_head_id' => $this->headId(), 'payee' => 'Lift Co', 'amount' => 5000, 'description' => 'Repair', 'treasury_medium' => 'CASH'];
    }

    private function enter(array $over = []): int
    {
        return $this->actingAs($this->maker, 'sanctum')->postJson('/api/expenses', $this->expenseBody($over))->assertSuccessful()->json('id');
    }

    private function material(array $over = []): int
    {
        return $this->actingAs($this->maker, 'sanctum')->postJson('/api/construction-materials', $over + [
            'date' => now()->toDateString(), 'item_name' => 'Cement', 'quantity' => 10, 'unit' => 'bag', 'unit_rate' => 500, 'treasury_medium' => 'CASH',
        ])->assertSuccessful()->json('id');
    }

    // ---- construction materials

    public function test_a_pending_material_purchase_can_be_corrected_with_its_expense(): void
    {
        $id = $this->material(['unit_rate' => 5000]); // a slipped zero

        $r = $this->actingAs($this->maker, 'sanctum')->putJson("/api/construction-materials/{$id}", [
            'date' => now()->toDateString(), 'item_name' => 'Cement', 'quantity' => 10, 'unit' => 'bag', 'unit_rate' => 500, 'transport_cost' => 200, 'treasury_medium' => 'BANK',
        ])->assertOk();

        $r->assertJsonPath('total_cost', '5200.0000')->assertJsonPath('treasury_medium', 'BANK');
        $p = ConstructionMaterialPurchase::findOrFail($id);
        $this->assertSame('5200.0000', (string) $p->expense->amount);
        $this->assertSame(1, Expense::count());
    }

    public function test_an_approved_material_purchase_is_locked_and_a_pending_one_can_be_cancelled(): void
    {
        $locked = $this->material();
        ConstructionMaterialPurchase::findOrFail($locked)->expense->update(['status' => 'APPROVED']);
        $open = $this->material();
        $api = $this->actingAs($this->maker, 'sanctum');

        $api->putJson("/api/construction-materials/{$locked}", ['date' => now()->toDateString(), 'item_name' => 'X', 'quantity' => 1, 'unit' => 'bag', 'unit_rate' => 1, 'treasury_medium' => 'CASH'])
            ->assertStatus(422)->assertJsonValidationErrors('purchase');
        $api->deleteJson("/api/construction-materials/{$locked}")->assertStatus(422);
        $api->deleteJson("/api/construction-materials/{$open}")->assertOk();

        $this->assertSame(1, ConstructionMaterialPurchase::count());
        $this->assertSame(1, Expense::count());
    }

    public function test_the_materials_list_says_which_can_be_corrected(): void
    {
        $open = $this->material();
        $this->material();
        $rows = collect($this->actingAs($this->maker, 'sanctum')->getJson('/api/construction-materials')->json())->keyBy('id');
        $this->assertTrue($rows[$open]['editable']);
    }

    // ---- plain expenses

    public function test_a_pending_expense_can_be_corrected(): void
    {
        $id = $this->enter(['amount' => 50000]);

        $this->actingAs($this->maker, 'sanctum')->putJson("/api/expenses/{$id}", $this->expenseBody(['amount' => 5200, 'payee' => 'Otis', 'treasury_medium' => 'BANK']))
            ->assertOk()->assertJsonPath('payee', 'Otis');

        $e = Expense::findOrFail($id);
        $this->assertSame('5200.0000', (string) $e->amount);
        $this->assertSame('BANK', $e->treasury_medium);
        $this->assertSame('PENDING', $e->status);
    }

    public function test_an_approved_or_signed_expense_cannot_be_corrected(): void
    {
        $approved = $this->enter();
        Expense::findOrFail($approved)->update(['status' => 'APPROVED']);
        $signed = $this->enter();
        Expense::findOrFail($signed)->approvals()->create(['approved_by' => $this->admin->id, 'approved_at' => now()]);
        $api = $this->actingAs($this->maker, 'sanctum');

        $api->putJson("/api/expenses/{$approved}", $this->expenseBody())->assertStatus(422)->assertJsonValidationErrors('expense');
        $api->putJson("/api/expenses/{$signed}", $this->expenseBody())->assertStatus(422)->assertJsonValidationErrors('expense');
    }

    public function test_an_expense_raised_from_a_purchase_must_be_corrected_at_its_source(): void
    {
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/gas/purchases', [
            'date' => now()->toDateString(), 'supplier_name' => 'Gas Co', 'quantity_kg' => 10, 'unit_price' => 100, 'treasury_medium' => 'CASH',
        ])->assertSuccessful();
        $expenseId = GasPurchase::first()->expense_id;

        $this->actingAs($this->maker, 'sanctum')->putJson("/api/expenses/{$expenseId}", $this->expenseBody())->assertStatus(422)->assertJsonValidationErrors('expense');
        $rows = collect($this->actingAs($this->maker, 'sanctum')->getJson('/api/expenses')->json('data'))->keyBy('id');
        $this->assertFalse($rows[$expenseId]['editable']);
    }

    public function test_the_list_marks_plain_pending_expenses_editable(): void
    {
        $id = $this->enter();

        $rows = collect($this->actingAs($this->maker, 'sanctum')->getJson('/api/expenses')->json('data'))->keyBy('id');
        $this->assertTrue($rows[$id]['editable']);
    }

    // ---- rejection

    public function test_an_approver_can_reject_a_pending_expense_with_a_reason_and_the_maker_is_told(): void
    {
        $id = $this->enter();

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/expenses/{$id}/reject", ['reason' => 'Bill is missing'])->assertOk();

        $this->assertNull(Expense::find($id));
        $this->assertSame(1, Expense::onlyTrashed()->count());
        $log = AuditLog::where('event', 'rejected')->firstOrFail();
        $this->assertSame('Bill is missing', $log->new_values['reason']);
        $note = $this->maker->fresh()->notifications->first();
        $this->assertSame('EXPENSE_REJECTED', $note->data['kind']);
        $this->assertStringContainsString('Bill is missing', $note->data['body']);
    }

    public function test_rejecting_a_purchase_takes_back_the_purchase_too(): void
    {
        $id = $this->material();

        $this->actingAs($this->admin, 'sanctum')->postJson('/api/expenses/'.ConstructionMaterialPurchase::findOrFail($id)->expense_id.'/reject', ['reason' => 'Wrong rate'])->assertOk();

        $this->assertSame(0, ConstructionMaterialPurchase::count());
        $this->assertSame(0, Expense::count());
    }

    public function test_a_rejection_needs_a_reason_and_the_approve_permission_and_a_pending_expense(): void
    {
        $id = $this->enter();

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/expenses/{$id}/reject", [])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->actingAs($this->maker, 'sanctum')->postJson("/api/expenses/{$id}/reject", ['reason' => 'because'])->assertForbidden();

        Expense::findOrFail($id)->update(['status' => 'APPROVED']);
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/expenses/{$id}/reject", ['reason' => 'too late'])->assertStatus(422)->assertJsonValidationErrors('expense');
        $this->assertNotNull(Expense::find($id));
    }
}
