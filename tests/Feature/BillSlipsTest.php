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
