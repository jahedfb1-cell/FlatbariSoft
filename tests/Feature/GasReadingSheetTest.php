<?php

namespace Tests\Feature;

use App\Models\BillingPeriod;
use App\Models\Flat;
use App\Models\GasMeterReading;
use App\Models\GasRateSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/** The month's meter readings on one sheet: last month's reading is already there, units = this reading - last reading, Excel in and out. */
class GasReadingSheetTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Flat $a;

    private Flat $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([\Database\Seeders\RoleSeeder::class, \Database\Seeders\MoneyCorePermissionSeeder::class, \Database\Seeders\CollectionHeadSeeder::class]);
        $this->admin = User::factory()->create(['role' => 'super_admin']);
        $this->admin->assignRole('super_admin');
        GasRateSetting::create(['effective_from' => '2026-01-01', 'method' => 'FIXED', 'rate' => 325, 'm3_per_kg' => 0.54, 'minimum_charge' => 0]);
        $this->a = Flat::factory()->create(['flat_no' => '2A', 'occupancy_status' => 'OWNER_OCCUPIED']);
        $this->b = Flat::factory()->create(['flat_no' => '2B', 'occupancy_status' => 'OWNER_OCCUPIED']);
    }

    private function api()
    {
        return $this->actingAs($this->admin, 'sanctum');
    }

    private function sheetSave(int $year, int $month, array $rows)
    {
        return $this->api()->postJson('/api/gas/readings/sheet', ['year' => $year, 'month' => $month, 'rows' => $rows]);
    }

    public function test_the_first_month_needs_the_meters_starting_reading_and_then_last_months_reading_carries_over(): void
    {
        // no earlier reading at all: a bare current reading is not enough
        $this->sheetSave(2026, 9, [['flat_no' => '2A', 'current_reading' => 322]])->assertStatus(422)->assertJsonValidationErrors('rows');
        $this->assertSame(0, GasMeterReading::count());

        // the starting reading is typed once: 219 -> 322
        $this->sheetSave(2026, 9, [['flat_no' => '2A', 'previous_reading' => 219, 'current_reading' => 322], ['flat_no' => '2B', 'previous_reading' => 0, 'current_reading' => 40]])->assertCreated();
        $r = GasMeterReading::whereHas('consumer.flat', fn ($q) => $q->where('flat_no', '2A'))->firstOrFail();
        $this->assertSame('103.0000', (string) $r->units_used);

        // next month only the new reading is typed; 322 is already the previous one
        $sheet = $this->api()->getJson('/api/gas/readings/sheet?year=2026&month=10')->assertOk();
        $row = collect($sheet->json('rows'))->firstWhere('flat_no', '2A');
        $this->assertSame('322.0000', $row['previous_reading']);
        $this->assertTrue($row['previous_known']);

        $this->sheetSave(2026, 10, [['flat_no' => '2A', 'current_reading' => 330], ['flat_no' => '2B', 'current_reading' => 41]])->assertCreated();
        $oct = GasMeterReading::whereHas('consumer.flat', fn ($q) => $q->where('flat_no', '2A'))->whereHas('billingPeriod', fn ($q) => $q->where('month', 10))->firstOrFail();
        $this->assertSame('8.0000', (string) $oct->units_used);
    }

    public function test_a_meter_cannot_run_backwards_and_one_wrong_row_saves_nothing(): void
    {
        $this->sheetSave(2026, 9, [['flat_no' => '2A', 'previous_reading' => 100, 'current_reading' => 150], ['flat_no' => '2B', 'previous_reading' => 10, 'current_reading' => 20]])->assertCreated();

        $this->sheetSave(2026, 10, [['flat_no' => '2A', 'current_reading' => 160], ['flat_no' => '2B', 'current_reading' => 15]])
            ->assertStatus(422)->assertJsonValidationErrors('rows');

        $this->assertSame(2, GasMeterReading::count()); // 2A's good October reading was not saved either
    }

    public function test_units_alone_work_out_the_reading_and_a_typed_previous_must_agree_with_the_system(): void
    {
        $this->sheetSave(2026, 9, [['flat_no' => '2A', 'previous_reading' => 100, 'current_reading' => 150]])->assertCreated();

        $this->sheetSave(2026, 10, [['flat_no' => '2A', 'units' => 12]])->assertCreated();
        $this->assertSame('162.0000', (string) GasMeterReading::latest('id')->first()->current_reading);

        $this->sheetSave(2026, 11, [['flat_no' => '2A', 'previous_reading' => 999, 'current_reading' => 1000]])
            ->assertStatus(422)->assertJsonValidationErrors('rows');
    }

    public function test_the_missing_flats_are_counted_for_bill_generation(): void
    {
        $this->sheetSave(2026, 9, [['flat_no' => '2A', 'previous_reading' => 0, 'current_reading' => 5]])->assertCreated();

        $this->api()->getJson('/api/gas/readings/sheet?year=2026&month=9')->assertJsonPath('missing_flats', 1);

        // the billing screen can now go on once everyone is in
        $this->sheetSave(2026, 9, [['flat_no' => '2B', 'previous_reading' => 0, 'current_reading' => 7]])->assertCreated();
        $this->api()->getJson('/api/gas/readings/sheet?year=2026&month=9')->assertJsonPath('missing_flats', 0);
        $this->assertSame(1, BillingPeriod::count());
    }

    public function test_the_excel_template_carries_last_months_readings_and_an_uploaded_sheet_is_checked(): void
    {
        $this->sheetSave(2026, 9, [['flat_no' => '2A', 'previous_reading' => 219, 'current_reading' => 322], ['flat_no' => '2B', 'previous_reading' => 0, 'current_reading' => 40]])->assertCreated();

        $file = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        file_put_contents($file, $this->api()->get('/api/gas/readings/template?year=2026&month=10')->assertOk()->streamedContent());
        $ws = IOFactory::load($file)->getActiveSheet();
        $this->assertSame('Flat No', $ws->getCell('A1')->getValue());
        $byFlat = [];
        foreach ($ws->toArray(null, false, false, false) as $i => $line) {
            if ($i > 0) {
                $byFlat[(string) $line[0]] = $line;
            }
        }
        $this->assertEquals(322, $byFlat['2A'][2]);
        $this->assertEquals(40, $byFlat['2B'][2]);

        // fill the current column and send it back
        $ws->setCellValue('D'.(array_search('2A', array_keys($byFlat)) + 2), 330);
        $row2b = array_search('2B', array_keys($byFlat)) + 2;
        $ws->setCellValue('D'.$row2b, 38); // below last month's 40: wrong
        (new Xlsx($ws->getParent()))->save($file);

        $check = $this->api()->post('/api/gas/readings/import', ['year' => 2026, 'month' => 10, 'file' => new UploadedFile($file, 'readings.xlsx', null, null, true)], ['Accept' => 'application/json'])->assertOk();
        $this->assertSame(1, $check->json('ready'));
        $this->assertCount(1, $check->json('problems'));
        $this->assertSame('8.0000', collect($check->json('rows'))->firstWhere('flat_no', '2A')['units_used']);
        $this->assertSame(2, GasMeterReading::count()); // checking saves nothing
    }

    public function test_a_viewer_cannot_save_readings(): void
    {
        $viewer = User::factory()->create(['role' => 'flat_owner']);

        $this->actingAs($viewer, 'sanctum')->postJson('/api/gas/readings/sheet', ['year' => 2026, 'month' => 9, 'rows' => [['flat_no' => '2A', 'previous_reading' => 0, 'current_reading' => 1]]])->assertForbidden();
    }
}
