<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\BillingPeriod;
use App\Models\ContributionHead;
use App\Models\Flat;
use App\Models\FlatParty;
use App\Models\Person;
use App\Models\User;
use App\Services\ContributionService;
use App\Services\PaymentService;
use App\Support\PdfName;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\CommunityHallSeeder;
use Database\Seeders\ContributionSeeder;
use Database\Seeders\MoneyCorePermissionSeeder;
use Database\Seeders\RoleCatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Every PDF is saved under a name that says what it is: flat no-owner name-what it was for, for the owners' papers. */
class PdfFileNameTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([ChartOfAccountsSeeder::class, RoleSeeder::class, MoneyCorePermissionSeeder::class, CommunityHallSeeder::class, RoleCatalogSeeder::class, ContributionSeeder::class] as $seeder) {
            $this->seed($seeder);
        }
        $this->admin = User::factory()->create(['role' => 'super_admin']);
        $this->admin->assignRole('super_admin');
    }

    /** The file name a response asks the browser to save under. */
    private function savedAs($response): string
    {
        $header = (string) $response->headers->get('Content-Disposition');
        if (preg_match("/filename\\*=utf-8''([^;]+)/i", $header, $m)) {
            return rawurldecode($m[1]);
        }
        preg_match('/filename="?([^";]+)"?/i', $header, $m);

        return $m[1] ?? '';
    }

    private function owned(string $no, string $nameEn, ?string $nameBn = null): Flat
    {
        $flat = Flat::factory()->create(['flat_no' => $no]);
        $person = Person::factory()->create(['name_en' => $nameEn, 'name_bn' => $nameBn ?? $nameEn]);
        FlatParty::create(['flat_id' => $flat->id, 'person_id' => $person->id, 'role' => 'OWNER', 'from_date' => '2026-01-01']);

        return $flat;
    }

    public function test_the_name_builder_keeps_names_readable_and_safe(): void
    {
        $this->assertSame('10A-Nazrul Islam-Subscription + Gas line.pdf', PdfName::make('10A', 'Nazrul Islam', 'Subscription + Gas line'));
        $this->assertSame('A B-C D.pdf', PdfName::make('A/B', 'C:D')); // illegal characters become a space
        $this->assertSame('10A-নজরুল ইসলাম-চাঁদা.pdf', PdfName::make('10A', 'নজরুল ইসলাম', 'চাঁদা')); // Bengali stays
        $this->assertSame('10A-Service Charge.pdf', PdfName::make('10A', null, 'Service Charge')); // no owner on record: that part is left out
        $this->assertSame('document.pdf', PdfName::make(null, ''));
    }

    public function test_a_payment_receipt_is_named_flat_owner_and_what_was_paid(): void
    {
        $flat = $this->owned('10A', 'Nazrul Islam');
        $period = BillingPeriod::create(['year' => 2026, 'month' => 9, 'status' => 'GENERATED']);
        $bill = Bill::create(['billing_period_id' => $period->id, 'flat_id' => $flat->id, 'service_charge' => 3000, 'gas_amount' => 1000, 'previous_due' => 0, 'total_payable' => 4000, 'paid_amount' => 0, 'due_date' => '2026-09-10', 'status' => 'UNPAID']);
        $service = app(PaymentService::class);
        $payment = $service->approve($service->create(['flat_id' => $flat->id, 'bill_id' => $bill->id, 'amount' => 4000, 'payment_date' => '2026-09-12', 'method' => 'CASH'], $this->admin->id), $this->admin->id);

        $res = $this->actingAs($this->admin, 'sanctum')->get("/api/payments/{$payment->id}/receipt")->assertOk();

        $this->assertSame('10A-Nazrul Islam-Service Charge + Gas Bill Sep 2026.pdf', $this->savedAs($res));
    }

    public function test_a_collection_receipt_is_named_after_the_heads_it_covers(): void
    {
        $flat = $this->owned('9C', 'Abul Kalam Azad');
        $rows = app(ContributionService::class)->recordMany($flat, ['received_on' => '2026-09-01', 'method' => 'CASH', 'lines' => [
            ['contribution_head_id' => ContributionHead::where('code', 'SUBSCRIPTION')->value('id'), 'amount' => 50000],
            ['contribution_head_id' => ContributionHead::where('code', 'GAS_LINE')->value('id'), 'amount' => 30000],
        ]], $this->admin->id);

        $single = $this->actingAs($this->admin, 'sanctum')->get("/api/contributions/collections/{$rows->first()->id}/receipt")->assertOk();
        $both = $this->actingAs($this->admin, 'sanctum')->get('/api/contributions/receipt?'.http_build_query(['ids' => $rows->pluck('id')->all()]))->assertOk();

        $this->assertSame('9C-Abul Kalam Azad-Subscription.pdf', $this->savedAs($single));
        $this->assertSame('9C-Abul Kalam Azad-Subscription + Gas line.pdf', $this->savedAs($both));
    }

    public function test_an_owners_ledger_and_the_reports_have_readable_names_too(): void
    {
        $flat = $this->owned('10A', 'Nazrul Islam', 'নজরুল ইসলাম');

        $ledger = $this->actingAs($this->admin, 'sanctum')->get("/api/contributions/flats/{$flat->id}/ledgers/pdf")->assertOk();
        $this->assertSame('10A-Nazrul Islam-Owner Collection Ledger.pdf', $this->savedAs($ledger));

        $this->assertSame('Construction cost-Collection Statement-'.now('Asia/Dhaka')->format('d M Y').'.pdf', $this->savedAs($this->actingAs($this->admin, 'sanctum')->get('/api/contributions/books/CONSTRUCTION/statement/pdf')->assertOk()));
        $this->assertSame('Trial Balance-'.now('Asia/Dhaka')->format('d M Y').'.pdf', $this->savedAs($this->actingAs($this->admin, 'sanctum')->get('/api/reports/trial-balance/pdf')->assertOk()));
    }

    public function test_the_pdf_carries_its_name_as_its_title(): void
    {
        $flat = $this->owned('10A', 'Nazrul Islam');
        $body = $this->actingAs($this->admin, 'sanctum')->get("/api/contributions/flats/{$flat->id}/ledgers/pdf")->assertOk()->getContent();

        // dompdf writes the title as UTF-16: "10A-Nazrul Islam-Owner Collection Ledger"
        $this->assertStringContainsString(mb_convert_encoding('10A-Nazrul Islam-Owner Collection Ledger', 'UTF-16BE', 'UTF-8'), $body);
    }
}
