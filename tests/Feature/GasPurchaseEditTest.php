<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\GasPurchase;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\ExpenseHeadSeeder;
use Database\Seeders\MoneyCorePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A gas purchase entered wrongly can be corrected or taken back - until its expense is approved. */
class GasPurchaseEditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

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
    }

    private function body(array $over = []): array
    {
        return $over + ['date' => now()->toDateString(), 'supplier_name' => 'Typed Gas', 'cylinders' => 1, 'cylinder_kg' => 45, 'price_per_cylinder' => 7700, 'treasury_medium' => 'CASH'];
    }

    private function buy(array $over = []): int
    {
        return $this->actingAs($this->admin, 'sanctum')->postJson('/api/gas/purchases', $this->body($over))->assertSuccessful()->json('id');
    }

    public function test_a_pending_purchase_can_be_corrected_and_its_expense_follows(): void
    {
        $id = $this->buy(['price_per_cylinder' => 77000]); // a slipped zero

        $r = $this->actingAs($this->admin, 'sanctum')->putJson("/api/gas/purchases/{$id}", $this->body(['cylinders' => 2, 'transport_cost' => 100, 'treasury_medium' => 'BANK']))->assertOk();

        $r->assertJsonPath('total_cost', '15500.0000')->assertJsonPath('quantity_kg', '90.0000')->assertJsonPath('treasury_medium', 'BANK');
        $p = GasPurchase::findOrFail($id);
        $this->assertSame('15500.0000', (string) $p->expense->amount);
        $this->assertSame('BANK', $p->expense->treasury_medium);
        $this->assertSame('PENDING', $p->expense->status);
        $this->assertSame(1, GasPurchase::count());
        $this->assertSame(1, Expense::count());
    }

    public function test_an_approved_purchase_can_no_longer_be_changed_or_cancelled(): void
    {
        $id = $this->buy();
        GasPurchase::findOrFail($id)->expense->update(['status' => 'APPROVED']);
        $api = $this->actingAs($this->admin, 'sanctum');

        $api->putJson("/api/gas/purchases/{$id}", $this->body(['price_per_cylinder' => 1]))->assertStatus(422)->assertJsonValidationErrors('purchase');
        $api->deleteJson("/api/gas/purchases/{$id}")->assertStatus(422)->assertJsonValidationErrors('purchase');
        $this->assertSame('7700.0000', (string) GasPurchase::findOrFail($id)->total_cost);
    }

    public function test_a_pending_purchase_can_be_cancelled(): void
    {
        $id = $this->buy();

        $this->actingAs($this->admin, 'sanctum')->deleteJson("/api/gas/purchases/{$id}")->assertOk();

        $this->assertSame(0, GasPurchase::count());
        $this->assertSame(0, Expense::count());
    }

    public function test_the_list_says_which_purchases_can_still_be_corrected(): void
    {
        $open = $this->buy();
        $shut = $this->buy();
        GasPurchase::findOrFail($shut)->expense->update(['status' => 'APPROVED']);

        $rows = collect($this->actingAs($this->admin, 'sanctum')->getJson('/api/gas/purchases')->json())->keyBy('id');
        $this->assertTrue($rows[$open]['editable']);
        $this->assertFalse($rows[$shut]['editable']);
    }

    public function test_a_user_without_gas_permission_cannot_edit_or_cancel(): void
    {
        $id = $this->buy();
        $other = User::factory()->create(['role' => 'resident']);

        $this->actingAs($other, 'sanctum')->putJson("/api/gas/purchases/{$id}", $this->body())->assertForbidden();
        $this->actingAs($other, 'sanctum')->deleteJson("/api/gas/purchases/{$id}")->assertForbidden();
    }
}
