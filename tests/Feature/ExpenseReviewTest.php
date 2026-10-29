<?php

namespace Tests\Feature;

use App\Models\ConstructionMaterialPurchase;
use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\ExpenseHeadSeeder;
use Database\Seeders\MoneyCorePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** An approver first sees the expense with its papers, then approves with a written note. */
class ExpenseReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(ExpenseHeadSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(MoneyCorePermissionSeeder::class);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->assignRole('admin');
    }

    private function newExpense(array $files = []): int
    {
        $res = $this->actingAs($this->admin, 'sanctum')->post('/api/expenses', [
            'date' => '2026-10-20', 'expense_head_id' => ExpenseHead::first()->id, 'payee' => 'Lift Co', 'amount' => '8000',
            'description' => 'Lift cable repair', 'treasury_medium' => 'CASH', 'attachments' => $files,
        ], ['Accept' => 'application/json'])->assertSuccessful();

        return $res->json('id');
    }

    public function test_papers_are_uploaded_with_the_expense_and_shown_on_its_detail(): void
    {
        $id = $this->newExpense([UploadedFile::fake()->create('bill.pdf', 120, 'application/pdf'), UploadedFile::fake()->image('voucher.jpg')]);

        $d = $this->actingAs($this->admin, 'sanctum')->getJson("/api/expenses/{$id}")->assertOk();
        $this->assertSame('Lift cable repair', $d->json('description'));
        $this->assertSame('Lift Co', $d->json('payee'));
        $this->assertSame(['bill.pdf', 'voucher.jpg'], collect($d->json('attachments'))->pluck('name')->all());
        $this->assertArrayNotHasKey('path', $d->json('attachments.0'));
        $this->assertSame(1, $d->json('approvals_needed'));
        $this->assertSame('PENDING', $d->json('status'));

        $fileId = $d->json('attachments.0.id');
        $this->actingAs($this->admin, 'sanctum')->get("/api/expenses/attachments/{$fileId}")->assertOk();
    }

    public function test_approving_needs_a_written_note_which_is_kept_and_shown(): void
    {
        $id = $this->newExpense();
        $api = $this->actingAs($this->admin, 'sanctum');

        $api->postJson("/api/expenses/{$id}/approve")->assertStatus(422)->assertJsonValidationErrors('note');
        $api->postJson("/api/expenses/{$id}/approve", ['note' => 'ab'])->assertStatus(422);
        $this->assertSame('PENDING', Expense::find($id)->status);

        $api->postJson("/api/expenses/{$id}/approve", ['note' => 'Bill matches the lift contract'])->assertOk()->assertJsonPath('status', 'APPROVED');

        $d = $api->getJson("/api/expenses/{$id}")->assertOk();
        $this->assertSame('Bill matches the lift contract', $d->json('approvals.0.note'));
        $this->assertNotEmpty($d->json('approvals.0.by'));
        $this->assertSame('APPROVED', $d->json('status'));
    }

    public function test_more_papers_can_be_added_later_and_wrong_ones_removed_until_approval(): void
    {
        $id = $this->newExpense();
        $api = $this->actingAs($this->admin, 'sanctum');

        $d = $api->post("/api/expenses/{$id}/attachments", ['attachments' => [UploadedFile::fake()->image('late.png')]], ['Accept' => 'application/json'])->assertOk();
        $fileId = $d->json('attachments.0.id');
        $this->assertCount(1, $d->json('attachments'));

        $api->deleteJson("/api/expenses/attachments/{$fileId}")->assertOk();
        $this->assertCount(0, $api->getJson("/api/expenses/{$id}")->json('attachments'));

        $api->post("/api/expenses/{$id}/attachments", ['attachments' => [UploadedFile::fake()->image('again.png')]], ['Accept' => 'application/json'])->assertOk();
        $api->postJson("/api/expenses/{$id}/approve", ['note' => 'Looks fine'])->assertOk();
        $keep = $api->getJson("/api/expenses/{$id}")->json('attachments.0.id');
        $api->deleteJson("/api/expenses/attachments/{$keep}")->assertStatus(422);   // part of the record now
    }

    public function test_only_pdf_and_images_up_to_the_limit_are_accepted(): void
    {
        $id = $this->newExpense();
        $api = $this->actingAs($this->admin, 'sanctum');

        $api->post("/api/expenses/{$id}/attachments", ['attachments' => [UploadedFile::fake()->create('x.exe', 10, 'application/x-msdownload')]], ['Accept' => 'application/json'])->assertStatus(422);
        $api->post("/api/expenses/{$id}/attachments", ['attachments' => [UploadedFile::fake()->create('big.pdf', 11000, 'application/pdf')]], ['Accept' => 'application/json'])->assertStatus(422);
        $api->post("/api/expenses/{$id}/attachments", ['attachments' => array_fill(0, 6, UploadedFile::fake()->image('a.png'))], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_a_material_purchase_expense_shows_its_bill_photo_as_the_source(): void
    {
        $purchase = app(\App\Services\ConstructionMaterialPurchaseService::class)->record([
            'date' => '2026-10-20', 'item_name' => 'Cement', 'quantity' => '10', 'unit' => 'bag', 'unit_rate' => '500',
            'supplier_name' => 'Akij', 'transport_cost' => '0', 'treasury_medium' => 'CASH',
        ], $this->admin->id);

        $d = $this->actingAs($this->admin, 'sanctum')->getJson("/api/expenses/{$purchase->expense_id}")->assertOk();
        $this->assertSame('materials', $d->json('source.type'));
        $this->assertSame($purchase->id, $d->json('source.purchase_id'));
        $this->assertFalse($d->json('source.has_invoice'));
    }

    public function test_viewers_cannot_approve_or_upload(): void
    {
        $id = $this->newExpense();
        $tenant = User::factory()->create(['role' => 'tenant']);
        $this->actingAs($tenant, 'sanctum')->getJson("/api/expenses/{$id}")->assertForbidden();
        $this->actingAs($tenant, 'sanctum')->postJson("/api/expenses/{$id}/approve", ['note' => 'sure'])->assertForbidden();
    }
}
