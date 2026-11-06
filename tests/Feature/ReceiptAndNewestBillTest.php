<?php

namespace Tests\Feature;

use App\Models\Contribution;
use App\Models\ContributionHead;
use App\Models\Flat;
use App\Models\User;
use App\Services\BillGenerationService;
use App\Services\ContributionService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\CollectionHeadSeeder;
use Database\Seeders\CommunityHallSeeder;
use Database\Seeders\ContributionSeeder;
use Database\Seeders\MoneyCorePermissionSeeder;
use Database\Seeders\RoleCatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A receipt PDF for every collection from the flat owners, and the billing register says where an older month's balance moved to. */
class ReceiptAndNewestBillTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([ChartOfAccountsSeeder::class, RoleSeeder::class, MoneyCorePermissionSeeder::class, CommunityHallSeeder::class, RoleCatalogSeeder::class, ContributionSeeder::class, CollectionHeadSeeder::class] as $seeder) {
            $this->seed($seeder);
        }
        $this->admin = User::factory()->create(['role' => 'super_admin']);
        $this->admin->assignRole('super_admin');
    }

    private function collect(Flat $flat, string $amount = '50000'): Contribution
    {
        return app(ContributionService::class)->record($flat, [
            'contribution_head_id' => ContributionHead::where('code', 'SUBSCRIPTION')->value('id'),
            'amount' => $amount, 'received_on' => '2026-09-01', 'method' => 'CASH',
        ], $this->admin->id);
    }

    public function test_a_collection_has_a_receipt_pdf_but_opening_and_reversal_rows_do_not(): void
    {
        $flat = Flat::factory()->create(['flat_no' => '10A']);
        $row = $this->collect($flat);

        $res = $this->actingAs($this->admin, 'sanctum')->get("/api/contributions/collections/{$row->id}/receipt")->assertOk();
        $this->assertStringStartsWith('%PDF', $res->getContent());
        $this->assertStringContainsString('application/pdf', $res->headers->get('Content-Type'));

        app(ContributionService::class)->importOpening(app(ContributionService::class)->book('CONSTRUCTION'), [['flat_no' => '10A', 'amounts' => ['SUBSCRIPTION' => 1000]]], '2026-01-01', $this->admin->id);
        $opening = Contribution::where('method', 'OPENING')->firstOrFail();
        $this->actingAs($this->admin, 'sanctum')->getJson("/api/contributions/collections/{$opening->id}/receipt")->assertStatus(422)->assertJsonValidationErrors('receipt');
    }

    public function test_a_resident_without_the_permission_cannot_print_receipts(): void
    {
        $row = $this->collect(Flat::factory()->create());
        $resident = User::factory()->create(['role' => 'flat_owner']);

        $this->actingAs($resident, 'sanctum')->getJson("/api/contributions/collections/{$row->id}/receipt")->assertForbidden();
    }

    public function test_an_older_months_bill_says_which_month_now_carries_its_balance(): void
    {
        $flat = Flat::factory()->create(['occupancy_status' => 'OWNER_OCCUPIED', 'service_charge_rate' => 3500]);
        $gen = app(BillGenerationService::class);
        $gen->generate(2026, 9, $this->admin->id, notify: false);
        $gen->generate(2026, 10, $this->admin->id, notify: false);

        $sept = collect($this->actingAs($this->admin, 'sanctum')->getJson('/api/billing/2026/9')->assertOk()->json())->firstWhere('flat_id', $flat->id);
        $oct = collect($this->actingAs($this->admin, 'sanctum')->getJson('/api/billing/2026/10')->assertOk()->json())->firstWhere('flat_id', $flat->id);

        $this->assertSame(2026, $sept['newest_year']);
        $this->assertSame(10, $sept['newest_month']);
        $this->assertNull($oct['newest_month']);
        $this->assertSame('7000.0000', $oct['total_payable']); // October's bill carries September's 3,500
    }

    public function test_a_month_nobody_has_billed_is_an_empty_register_not_an_error(): void
    {
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/billing/2030/1')->assertOk()->assertExactJson([]);
    }
}
