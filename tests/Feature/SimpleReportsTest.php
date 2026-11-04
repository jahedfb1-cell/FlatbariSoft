<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\BillingPeriod;
use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\Flat;
use App\Models\Payment;
use App\Models\User;
use App\Services\BillBalanceService;
use App\Services\TreasuryService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\ExpenseHeadSeeder;
use Database\Seeders\MoneyCorePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TreasurySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The plain-language reports, and the rule that a flat's balance is its latest bill (never every unpaid bill added up). */
class SimpleReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Flat $a1;

    private Flat $a2;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([ChartOfAccountsSeeder::class, ExpenseHeadSeeder::class, RoleSeeder::class, MoneyCorePermissionSeeder::class, TreasurySeeder::class] as $s) {
            $this->seed($s);
        }
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->assignRole('admin');
        $this->a1 = Flat::factory()->create(['flat_no' => '2A']);
        $this->a2 = Flat::factory()->create(['flat_no' => '2B']);

        // 2A: October 3000 (unpaid), November 3000 + 3000 carried = 6000 with 1000 paid; 2B: November 3000 fully paid
        $oct = BillingPeriod::create(['year' => 2026, 'month' => 10, 'status' => 'GENERATED']);
        $nov = BillingPeriod::create(['year' => 2026, 'month' => 11, 'status' => 'GENERATED']);
        $bill = fn ($period, $flat, $prev, $service, $gas, $paid) => Bill::create([
            'billing_period_id' => $period->id, 'flat_id' => $flat->id, 'service_charge' => $service, 'gas_amount' => $gas, 'other_charges' => 0, 'late_fee' => 0,
            'previous_due' => $prev, 'total_payable' => $prev + $service + $gas, 'paid_amount' => $paid, 'due_date' => '2026-11-10', 'status' => $paid > 0 ? 'PARTIAL' : 'UNPAID',
        ]);
        $bill($oct, $this->a1, 0, 3000, 0, 0);
        $bill($nov, $this->a1, 3000, 2400, 600, 1000);
        $bill($nov, $this->a2, 0, 3000, 0, 3000);
    }

    public function test_a_flats_balance_is_its_latest_bill_not_every_unpaid_bill(): void
    {
        $balances = app(BillBalanceService::class);

        $this->assertSame('5000.0000', $balances->totalDue()); // 2A owes 6000 - 1000 once; adding October's 3000 again would say 8000
    }

    public function test_the_dues_report_lists_only_flats_that_owe_largest_first(): void
    {
        $r = $this->actingAs($this->admin, 'sanctum')->getJson('/api/reports/simple/dues')->assertOk()->json();

        $this->assertCount(1, $r['rows']);
        $this->assertSame('2A', $r['rows'][0]['flat']);
        $this->assertSame('5000.00', $r['rows'][0]['due']);
        $this->assertSame('5000.00', $r['totals']['due']);
        $this->assertSame('500.00', $r['rows'][0]['gas']); // gas is a tenth of that bill (600 of 6000), so a tenth of what is left
    }

    public function test_the_collection_report_counts_only_approved_payments_in_the_range(): void
    {
        $pay = fn ($amount, $date, $status, $method = 'CASH') => Payment::create([
            'flat_id' => $this->a1->id, 'amount' => $amount, 'payment_date' => $date, 'method' => $method, 'reference_no' => $method === 'CASH' ? null : 'TX1',
            'status' => $status, 'received_by' => $this->admin->id, 'receipt_no' => $status === 'APPROVED' ? 'MR-'.$amount : null,
        ]);
        $pay(1000, '2026-11-05', 'APPROVED');
        $pay(500, '2026-11-06', 'APPROVED', 'BKASH');
        $pay(700, '2026-11-07', 'PENDING');
        $pay(900, '2026-10-01', 'APPROVED');

        $r = $this->actingAs($this->admin, 'sanctum')->getJson('/api/reports/simple/collections?from=2026-11-01&to=2026-11-30')->assertOk()->json();

        $this->assertSame('1500.00', $r['totals']['amount']);
        $this->assertCount(2, $r['rows']);
        $labels = array_column($r['summary'], 'value', 'label');
        $this->assertSame('1000.00', $labels['নগদ']);
        $this->assertSame('500.00', $labels['bKash']);
    }

    public function test_the_expense_report_groups_approved_spending_by_head(): void
    {
        $head = ExpenseHead::where('code', 'UTILITY')->first();
        $mk = fn ($amount, $status) => Expense::create(['date' => '2026-11-03', 'expense_head_id' => $head->id, 'payee' => 'X', 'amount' => $amount, 'treasury_medium' => 'CASH', 'fund_type' => 'OPERATING', 'status' => $status, 'created_by' => $this->admin->id, 'voucher_no' => $status === 'APPROVED' ? 'V'.$amount : null]);
        $mk(4000, 'APPROVED');
        $mk(1000, 'APPROVED');
        $mk(9999, 'PENDING');

        $r = $this->actingAs($this->admin, 'sanctum')->getJson('/api/reports/simple/expenses?from=2026-11-01&to=2026-11-30')->assertOk()->json();

        $this->assertSame('5000.00', $r['totals']['amount']);
        $this->assertSame('5000.00', array_column($r['summary'], 'value', 'label')[$head->name_bn]);
    }

    public function test_one_flats_statement_lists_its_bills_and_current_balance(): void
    {
        $r = $this->actingAs($this->admin, 'sanctum')->getJson("/api/reports/simple/flat?flat_id={$this->a1->id}&from=2026-10-01&to=2026-11-30")->assertOk()->json();

        $this->assertCount(2, $r['rows']);
        $this->assertSame('3000.00', $r['rows'][1]['previous']);
        $this->assertSame('5000.00', array_column($r['summary'], 'value', 'label')['এখন মোট বাকি (সর্বশেষ বিল অনুযায়ী)']);
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/reports/simple/flat')->assertStatus(422); // a flat is needed
    }

    public function test_the_cash_book_carries_the_opening_balance_and_a_running_balance(): void
    {
        $treasury = app(TreasuryService::class);
        $treasury->openingBalance('OPERATING', '10000', '0', '2026-10-01', null, $this->admin->id);
        $treasury->openingBalance('OPERATING', '2500', '0', '2026-11-02', null, $this->admin->id);

        $r = $this->actingAs($this->admin, 'sanctum')->getJson('/api/reports/simple/cashbook?medium=CASH&from=2026-11-01&to=2026-11-30')->assertOk()->json();

        $labels = array_column($r['summary'], 'value', 'label');
        $this->assertSame('10000.00', $labels['শুরুর স্থিতি']);
        $this->assertSame('2500.00', $labels['মোট জমা']);
        $this->assertSame('12500.00', $labels['শেষ স্থিতি']);
        $this->assertSame('12500.00', end($r['rows'])['balance']);
    }

    public function test_every_report_comes_out_as_pdf_and_excel_and_viewers_are_refused(): void
    {
        $api = $this->actingAs($this->admin, 'sanctum');
        foreach (['dues', 'collections', 'expenses', 'cashbook'] as $type) {
            $this->assertStringContainsString('pdf', $api->get("/api/reports/simple/{$type}/pdf")->assertOk()->headers->get('content-type'));
            $api->get("/api/reports/simple/{$type}/excel")->assertOk();
        }
        $api->getJson('/api/reports/simple/nonsense')->assertNotFound();
        $api->getJson('/api/reports/simple/collections?from=2026-12-01&to=2026-11-01')->assertStatus(422);

        $tenant = User::factory()->create(['role' => 'tenant']);
        $tenant->assignRole('tenant');
        $this->actingAs($tenant, 'sanctum')->getJson('/api/reports/simple/dues')->assertForbidden();
    }

    public function test_an_old_months_bill_cannot_be_paid_once_a_newer_one_exists(): void
    {
        $oct = Bill::whereHas('billingPeriod', fn ($q) => $q->where('month', 10))->first();

        $this->actingAs($this->admin, 'sanctum')->postJson('/api/payments', [
            'flat_id' => $this->a1->id, 'bill_id' => $oct->id, 'amount' => 100, 'payment_date' => now()->toDateString(), 'method' => 'CASH',
        ])->assertStatus(422)->assertJsonValidationErrors('bill_id');
    }
}
