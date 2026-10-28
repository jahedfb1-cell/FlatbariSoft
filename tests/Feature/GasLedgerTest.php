<?php

namespace Tests\Feature;

use App\Models\GasPurchase;
use App\Models\User;
use App\Services\GasPurchaseService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\ExpenseHeadSeeder;
use Database\Seeders\MoneyCorePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MakesInvoiceImages;
use Tests\TestCase;

/** The gas purchase ledger: running totals, vouchers, bill-photo status, and the PDF/Excel exports. */
class GasLedgerTest extends TestCase
{
    use MakesInvoiceImages;
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

    private function buy(string $date, string $kg, string $rate, string $transport = '0', ?UploadedFile $photo = null, string $supplier = 'Bashundhara'): GasPurchase
    {
        return app(GasPurchaseService::class)->record(array_filter([
            'date' => $date, 'supplier_name' => $supplier, 'quantity_kg' => $kg, 'unit_price' => $rate,
            'transport_cost' => $transport, 'treasury_medium' => 'CASH', 'invoice_image' => $photo,
        ], fn ($v) => $v !== null), $this->admin->id);
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('bill.jpg', $this->makeJpeg(1200, 900));
    }

    public function test_ledger_lists_purchases_in_date_order_with_running_totals(): void
    {
        $this->buy('2026-09-20', '100', '95');           // 9500
        $this->buy('2026-09-05', '400', '95', '500');    // 38500
        $this->buy('2026-10-02', '200', '100');          // 20000

        $res = $this->actingAs($this->admin, 'sanctum')->getJson('/api/gas/purchases/ledger')->assertOk();
        $rows = $res->json('rows');

        $this->assertSame(['2026-09-05', '2026-09-20', '2026-10-02'], array_column($rows, 'date'));
        $this->assertSame('400.0000', $rows[0]['running_kg']);
        $this->assertSame('500.0000', $rows[1]['running_kg']);
        $this->assertSame('700.0000', $rows[2]['running_kg']);
        $this->assertSame('38500.0000', $rows[0]['running_cost']);
        $this->assertSame('68000.0000', $rows[2]['running_cost']);
        $this->assertStringStartsWith('GP-', $rows[0]['ref']);
    }

    public function test_totals_cover_kg_cost_transport_and_the_real_cost_per_kg(): void
    {
        $this->buy('2026-09-05', '400', '95', '500');
        $this->buy('2026-09-20', '100', '100');

        $t = $this->actingAs($this->admin, 'sanctum')->getJson('/api/gas/purchases/ledger')->json('totals');

        $this->assertSame(2, $t['count']);
        $this->assertSame('500.0000', $t['quantity_kg']);
        $this->assertSame('48000.0000', $t['gas_cost']);        // 400x95 + 100x100
        $this->assertSame('500.0000', $t['transport_cost']);
        $this->assertSame('48500.0000', $t['total_cost']);
        $this->assertSame('97.0000', $t['avg_cost_per_kg']);     // 48500 / 500, delivery included
    }

    public function test_the_date_range_narrows_the_rows_but_overall_still_counts_everything(): void
    {
        $this->buy('2026-08-10', '100', '90');
        $this->buy('2026-09-10', '200', '95');
        $this->buy('2026-10-10', '300', '100');

        $res = $this->actingAs($this->admin, 'sanctum')->getJson('/api/gas/purchases/ledger?from=2026-09-01&to=2026-09-30')->assertOk();

        $this->assertSame(1, $res->json('totals.count'));
        $this->assertSame('200.0000', $res->json('totals.quantity_kg'));
        $this->assertSame(3, $res->json('overall.count'));
        $this->assertSame('600.0000', $res->json('overall.quantity_kg'));
    }

    public function test_monthly_summary_groups_by_month(): void
    {
        $this->buy('2026-09-05', '100', '95');
        $this->buy('2026-09-25', '100', '95');
        $this->buy('2026-10-05', '50', '100');

        $months = $this->actingAs($this->admin, 'sanctum')->getJson('/api/gas/purchases/ledger')->json('by_month');

        $this->assertSame(['2026-09', '2026-10'], array_column($months, 'month'));
        $this->assertSame(2, $months[0]['count']);
        $this->assertSame('200.0000', $months[0]['quantity_kg']);
    }

    public function test_voucher_number_appears_only_after_the_expense_is_approved(): void
    {
        $pending = $this->buy('2026-09-05', '100', '95');
        $approved = $this->buy('2026-09-06', '100', '95');
        $approved->expense->update(['status' => 'APPROVED', 'voucher_no' => 'VOU-000123']);

        $rows = collect($this->actingAs($this->admin, 'sanctum')->getJson('/api/gas/purchases/ledger')->json('rows'))->keyBy('id');

        $this->assertNull($rows[$pending->id]['voucher_no']);
        $this->assertSame('PENDING', $rows[$pending->id]['status']);
        $this->assertSame('VOU-000123', $rows[$approved->id]['voucher_no']);
        $this->assertSame('APPROVED', $rows[$approved->id]['status']);

        $t = $this->getJson('/api/gas/purchases/ledger')->json('totals');
        $this->assertSame('9500.0000', $t['approved_cost']);
        $this->assertSame('9500.0000', $t['pending_cost']);
    }

    public function test_it_reports_which_purchases_have_a_bill_photo(): void
    {
        $with = $this->buy('2026-09-05', '100', '95', '0', $this->photo());
        $without = $this->buy('2026-09-06', '100', '95');

        $res = $this->actingAs($this->admin, 'sanctum')->getJson('/api/gas/purchases/ledger')->assertOk();
        $rows = collect($res->json('rows'))->keyBy('id');

        $this->assertTrue($rows[$with->id]['has_invoice']);
        $this->assertFalse($rows[$without->id]['has_invoice']);
        $this->assertSame(1, $res->json('totals.with_invoice'));
        $this->assertSame(1, $res->json('totals.without_invoice'));
    }

    public function test_an_empty_ledger_is_all_zeros_not_an_error(): void
    {
        $res = $this->actingAs($this->admin, 'sanctum')->getJson('/api/gas/purchases/ledger')->assertOk();

        $this->assertSame([], $res->json('rows'));
        $this->assertSame('0.0000', $res->json('totals.avg_cost_per_kg'));
        $this->assertSame(0, $res->json('overall.count'));
    }

    public function test_range_validation(): void
    {
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/gas/purchases/ledger?from=2026-10-01&to=2026-09-01')
            ->assertStatus(422)->assertJsonValidationErrors('to');
    }

    // ---- exports

    public function test_pdf_is_a_valid_document_and_vouchers_add_one_photo_page_each(): void
    {
        $this->buy('2026-09-05', '100', '95', '0', $this->photo());
        $this->buy('2026-09-06', '100', '95', '0', $this->photo());
        $this->buy('2026-09-07', '100', '95'); // no photo: listed in the ledger, no page of its own
        $this->actingAs($this->admin, 'sanctum');

        $plain = $this->get('/api/gas/purchases/ledger/pdf')->assertOk();
        $this->assertSame('application/pdf', $plain->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $plain->getContent());
        $this->assertSame(0, substr_count($plain->getContent(), '/Subtype /Image'));

        $withVouchers = $this->get('/api/gas/purchases/ledger/pdf?vouchers=1')->assertOk();
        $this->assertSame(2, substr_count($withVouchers->getContent(), '/Subtype /Image'));
        $this->assertGreaterThan(strlen($plain->getContent()), strlen($withVouchers->getContent()));
    }

    public function test_excel_export_downloads(): void
    {
        $this->buy('2026-09-05', '100', '95');

        $res = $this->actingAs($this->admin, 'sanctum')->get('/api/gas/purchases/ledger/excel')->assertOk();

        $this->assertStringContainsString('spreadsheetml', $res->headers->get('Content-Type'));
    }

    public function test_the_ledger_needs_the_gas_view_permission(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant']);
        $tenant->assignRole('tenant');

        foreach (['', '/pdf', '/excel'] as $suffix) {
            $this->actingAs($tenant, 'sanctum')->getJson('/api/gas/purchases/ledger'.$suffix)->assertForbidden();
        }
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/gas/purchases/ledger')->assertUnauthorized();
    }
}
