<?php

namespace Tests\Feature;

use App\Models\BillingPeriod;
use App\Models\Flat;
use App\Models\GasConsumer;
use App\Models\GasMeterReading;
use App\Models\GasRateSetting;
use App\Models\User;
use App\Services\GasBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GasReadingCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_typing_a_months_reading_again_corrects_it_instead_of_failing(): void
    {
        GasRateSetting::create(['effective_from' => '2026-01-01', 'method' => 'FIXED', 'rate' => 330, 'm3_per_kg' => 0.54]);
        $service = app(GasBillingService::class);
        $user = User::factory()->create();
        $consumer = GasConsumer::create(['flat_id' => Flat::factory()->create()->id, 'name' => 'T', 'is_internal' => false, 'active' => true]);
        $aug = BillingPeriod::create(['year' => 2026, 'month' => 8, 'status' => 'DRAFT']);
        $sep = BillingPeriod::create(['year' => 2026, 'month' => 9, 'status' => 'DRAFT']);

        $service->recordReading($consumer, $aug->id, 100, '2026-08-05', $user->id);
        $service->recordReading($consumer, $sep->id, 130, '2026-09-05', $user->id);
        $fixed = $service->recordReading($consumer, $sep->id, 140, '2026-09-06', $user->id);

        $this->assertSame(2, GasMeterReading::where('gas_consumer_id', $consumer->id)->count());
        $this->assertSame('100.0000', $fixed->previous_reading);
        $this->assertSame('40.0000', $fixed->units_used);
    }
}
