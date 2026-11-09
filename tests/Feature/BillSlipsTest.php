<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\FlatParty;
use App\Models\GasRateSetting;
use App\Models\Person;
use App\Models\User;
use App\Services\BillGenerationService;
use App\Services\BillPrintService;
use App\Services\GasReadingSheetService;
use Database\Seeders\CollectionHeadSeeder;
use Database\Seeders\MoneyCorePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The month's bills on paper: four to an A4 page, all flats in one file or one flat alone, with the gas meter figures. */
class BillSlipsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, MoneyCorePermissionSeeder::class, CollectionHeadSeeder::class]);
        $this->admin = User::factory()->create(['role' => 'super_admin']);
        $this->admin->assignRole('super_admin');
        GasRateSetting::create(['effective_from' => '2026-01-01', 'method' => 'FIXED', 'rate' => 325, 'm3_per_kg' => 0.54, 'minimum_charge' => 0]);
    }

    /** $count flats, readings entered (previous 219 -> current 322 for the first), bills made for September 2026. */
    private function september(int $count): array
    {
        $flats = [];
        for ($i = 0; $i < $count; $i++) {
            $flats[] = Flat::factory()->create(['flat_no' => (2 + intdiv($i, 5)).chr(65 + $i % 5), 'occupancy_status' => 'OWNER_OCCUPIED', 'service_charge_rate' => 3500]);
        }
        $person = Person::factory()->create(['name_en' => 'Nazrul Islam']);
        FlatParty::create(['flat_id' => $flats[0]->id, 'person_id' => $person->id, 'role' => 'OWNER', 'from_date' => '2026-01-01']);

        $sheet = app(GasReadingSheetService::class);
        $sheet->save(2026, 9, collect($flats)->map(fn ($f, $i) => ['flat_no' => $f->flat_no, 'previous_reading' => $i === 0 ? 219 : 0, 'current_reading' => $i === 0 ? 322 : 5])->all(), '2026-09-30', $this->admin->id);
        app(BillGenerationService::class)->generate(2026, 9, $this->admin->id, notify: false);

        return $flats;
    }

    public function test_the_register_shows_units_and_per_unit_rate_beside_the_gas_amount(): void
    {
        $flats = $this->september(2); // first flat: 219 -> 322 = 103 units at 325
        $rows = collect($this->actingAs($this->admin, 'sanctum')->getJson('/api/billing/2026/9')->assertOk()->json());
        $first = $rows->firstWhere('flat_no', $flats[0]->flat_no);

        $this->assertEquals(103, $first['gas_units']);
        $this->assertEquals(325, $first['gas_rate']);
        $this->assertEquals(103 * 325, $first['gas_amount']);
    }

    public function test_the_gas_page_lists_every_month_of_the_year_with_its_readings(): void
    {
        $this->september(2);
        $r = $this->actingAs($this->admin, 'sanctum')->getJson('/api/gas/months?fiscal_year=2026')->assertOk();

        $this->assertSame('2026-27', $r->json('label'));
        $this->assertCount(12, $r->json('months'));
        $this->assertSame([7, 8, 9], array_slice(array_column($r->json('months'), 'month'), 0, 3));
        $sep = $r->json('months.2');
        $this->assertSame(2, $sep['flats_read']);
        $this->assertSame(2, $sep['flats_total']);
        $this->assertEquals(108, $sep['used_m3']); // 103 + 5
        $this->assertSame(0, $r->json('months.0.flats_read')); // July: nothing read
    }

    public function test_flats_come_in_building_order_on_the_register_and_on_paper(): void
    {
        // typed in a jumbled order, with 10A after 9B: text order would put 10A before 2A
        $nos = ['10A', '2B', '3A', '2A', '9B', '1C'];
        $flats = collect($nos)->map(fn ($no) => Flat::factory()->create(['flat_no' => $no, 'occupancy_status' => 'OWNER_OCCUPIED', 'service_charge_rate' => 3500]));
        app(GasReadingSheetService::class)->save(2026, 9, $flats->map(fn ($f) => ['flat_no' => $f->flat_no, 'previous_reading' => 0, 'current_reading' => 5])->all(), '2026-09-30', $this->admin->id);
        app(BillGenerationService::class)->generate(2026, 9, $this->admin->id, notify: false);
        $expected = ['1C', '2A', '2B', '3A', '9B', '10A'];

        $register = $this->actingAs($this->admin, 'sanctum')->getJson('/api/billing/2026/9')->assertOk()->json();
        $this->assertSame($expected, array_column($register, 'flat_no'));
        $this->assertSame($expected, app(BillPrintService::class)->slips(2026, 9)->pluck('flat_no')->all());
    }

    public function test_every_slip_carries_the_service_charge_and_the_meter_figures(): void
    {
        $flats = $this->september(2);

        $slip = app(BillPrintService::class)->slips(2026, 9)->firstWhere('flat_no', $flats[0]->flat_no);

        $this->assertSame('Nazrul Islam', $slip['owner']);
        $this->assertSame('219.0000', $slip['gas_previous']);
        $this->assertSame('322.0000', $slip['gas_current']);
        $this->assertSame('103.0000', $slip['gas_units']);
        $this->assertSame('325.00', $slip['gas_rate']);
        $this->assertSame('33475.0000', $slip['gas_amount']);
        $this->assertSame('36975.0000', $slip['total_payable']); // 3,500 + 103 x 325
    }

    public function test_all_flats_come_in_one_pdf_four_to_a_page_in_building_order(): void
    {
        $this->september(9); // 9 slips = 3 pages (4 + 4 + 1)

        $res = $this->actingAs($this->admin, 'sanctum')->get('/api/billing/2026/9/pdf')->assertOk();
        $pdf = $res->getContent();

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertSame(3, preg_match_all('/\/Type\s*\/Page\b(?!s)/', $pdf));
        $this->assertSame('All Flat Bills-Sep 2026.pdf', $this->savedAs($res));
        $this->assertSame(['2A', '2B', '2C', '2D', '2E', '3A', '3B', '3C', '3D'], app(BillPrintService::class)->slips(2026, 9)->pluck('flat_no')->all());
    }

    public function test_the_project_offices_gas_is_printed_with_the_flats_but_never_payable(): void
    {
        $flats = $this->september(2); // flat 1: 219 -> 322 = 103 units
        $office = \App\Models\GasConsumer::create(['name' => 'Project Office', 'is_internal' => true, 'active' => true]);
        $period = \App\Models\BillingPeriod::where('year', 2026)->where('month', 9)->first();
        app(\App\Services\GasBillingService::class)->recordReading($office, $period->id, 5, '2026-09-30', $this->admin->id, null, '0');

        $slips = app(BillPrintService::class)->slips(2026, 9);

        $this->assertSame(3, $slips->count()); // two flats and the office, the office last
        $o = $slips->last();
        $this->assertTrue($o['is_office']);
        $this->assertSame('Project Office', $o['flat_no']);
        $this->assertSame('5.0000', $o['gas_units']);
        $this->assertSame('0.00', $o['due'] === '0' ? '0.00' : $o['due']);
        $this->assertEquals(5 * (float) $o['gas_rate'], (float) $o['gas_amount']);
        // one flat's own print never carries the office
        $this->assertSame(1, app(BillPrintService::class)->slips(2026, 9, $flats[0]->id)->count());
        $this->actingAs($this->admin, 'sanctum')->get('/api/billing/2026/9/pdf')->assertOk();
    }

    public function test_one_flat_alone_is_named_flat_owner_and_bill_month(): void
    {
        $flats = $this->september(3);

        $res = $this->actingAs($this->admin, 'sanctum')->get('/api/billing/2026/9/pdf?flat_id='.$flats[0]->id)->assertOk();

        $this->assertSame('2A-Nazrul Islam-Bill Sep 2026.pdf', $this->savedAs($res));
        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page\b(?!s)/', $res->getContent()));
    }

    public function test_a_month_without_bills_is_refused_and_viewers_cannot_print(): void
    {
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/billing/2026/9/pdf')->assertStatus(422)->assertJsonValidationErrors('month');

        $resident = User::factory()->create(['role' => 'flat_owner']);
        $this->actingAs($resident, 'sanctum')->getJson('/api/billing/2026/9/pdf')->assertForbidden();
    }

    private function savedAs($response): string
    {
        $header = (string) $response->headers->get('Content-Disposition');
        if (preg_match("/filename\\*=utf-8''([^;]+)/i", $header, $m)) {
            return rawurldecode($m[1]);
        }
        preg_match('/filename="?([^";]+)"?/i', $header, $m);

        return $m[1] ?? '';
    }
}
