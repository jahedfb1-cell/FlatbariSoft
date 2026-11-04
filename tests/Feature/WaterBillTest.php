<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\ExpenseHeadSeeder;
use Database\Seeders\MoneyCorePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TreasurySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The monthly Dhaka WASA bill: entered as printed, total worked out on the server, posted as an ordinary expense. */
class WaterBillTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        foreach ([ChartOfAccountsSeeder::class, ExpenseHeadSeeder::class, RoleSeeder::class, MoneyCorePermissionSeeder::class, TreasurySeeder::class] as $s) {
            $this->seed($s);
        }
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->assignRole('admin');
    }

    private function body(array $over = []): array
    {
        return $over + [
            'bill_year' => 2026, 'bill_month' => 10, 'account_no' => '123456', 'bill_no' => 'B-77',
            'previous_reading' => 1200, 'current_reading' => 1450,
            'water_charge' => 18000, 'sewerage_charge' => 18000, 'vat' => 5400, 'other_charge' => 0, 'late_fee' => 600,
            'due_date' => '2026-11-15', 'treasury_medium' => 'CASH',
        ];
    }

    public function test_posting_a_bill_works_out_the_total_and_creates_a_pending_expense(): void
    {
        $res = $this->actingAs($this->admin, 'sanctum')->postJson('/api/water-bills', $this->body())->assertCreated();

        $res->assertJsonPath('total', '42000.0000')->assertJsonPath('consumption', '250.0000')->assertJsonPath('expense_status', 'PENDING');
        $expense = Expense::find($res->json('expense_id'));
        $this->assertSame('42000.0000', (string) $expense->amount);
        $this->assertSame('OPERATING', $expense->fund_type);
        $this->assertSame('ঢাকা ওয়াসা', $expense->payee);
    }

    public function test_one_bill_per_month_and_account_and_the_readings_must_make_sense(): void
    {
        $api = $this->actingAs($this->admin, 'sanctum');
        $api->postJson('/api/water-bills', $this->body())->assertCreated();

        $api->postJson('/api/water-bills', $this->body())->assertStatus(422)->assertJsonValidationErrors('bill_month');
        $api->postJson('/api/water-bills', $this->body(['account_no' => '999']))->assertCreated(); // a second connection is fine
        $api->postJson('/api/water-bills', $this->body(['bill_month' => 9, 'current_reading' => 10]))->assertStatus(422)->assertJsonValidationErrors('current_reading');
        $api->postJson('/api/water-bills', $this->body(['bill_month' => 8, 'water_charge' => 0, 'sewerage_charge' => 0, 'vat' => 0, 'late_fee' => 0]))->assertStatus(422);
    }

    public function test_the_bill_papers_are_attached_to_the_expense(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->post('/api/water-bills', $this->body(['attachments' => [UploadedFile::fake()->create('wasa.pdf', 100, 'application/pdf')]]), ['Accept' => 'application/json'])
            ->assertCreated();

        $this->assertSame(1, Expense::first()->attachments()->count());
    }

    public function test_a_bill_can_be_corrected_until_its_expense_is_approved(): void
    {
        $api = $this->actingAs($this->admin, 'sanctum');
        $bill = $api->postJson('/api/water-bills', $this->body())->json();

        $api->putJson("/api/water-bills/{$bill['id']}", $this->body(['water_charge' => 20000]))->assertOk()->assertJsonPath('total', '44000.0000');
        $this->assertSame('44000.0000', (string) Expense::find($bill['expense_id'])->amount);

        // fund the project, approve it by a second person - after that the bill is in the books
        $treasury = app(\App\Services\TreasuryService::class);
        $treasury->openingBalance('OPERATING', '500000', '0', '2026-01-01', null, $this->admin->id);
        $checker = User::factory()->create(['role' => 'admin']);
        $checker->assignRole('admin');
        $this->actingAs($checker, 'sanctum')->postJson("/api/expenses/{$bill['expense_id']}/approve", ['note' => 'bill seen'])->assertOk();

        $api->putJson("/api/water-bills/{$bill['id']}", $this->body())->assertStatus(422)->assertJsonValidationErrors('bill');
    }

    public function test_the_year_view_shows_every_month_and_where_it_stands(): void
    {
        $api = $this->actingAs($this->admin, 'sanctum');
        $api->postJson('/api/water-bills', $this->body())->assertCreated(); // October 2026, unpaid, due 15 Nov 2026

        $r = $api->getJson('/api/water-bills?fiscal_year=2026')->assertOk();
        $r->assertJsonCount(12, 'months')->assertJsonPath('label', '2026-27')->assertJsonPath('total', '42000.0000')->assertJsonPath('paid', '0.0000');
        $this->assertSame('NOT_POSTED', $r->json('months.0.status'));   // July
        $this->assertSame('PENDING', $r->json('months.3.status'));      // October, posted and waiting for approval
        $this->assertSame('1450.0000', $r->json('last.current_reading')); // the next bill starts from here
    }

    public function test_viewers_cannot_post(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant']);
        $tenant->assignRole('tenant');

        $this->actingAs($tenant, 'sanctum')->postJson('/api/water-bills', $this->body())->assertForbidden();
        $this->actingAs($tenant, 'sanctum')->getJson('/api/water-bills')->assertForbidden();
    }
}
