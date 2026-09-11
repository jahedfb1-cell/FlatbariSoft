<?php

namespace Database\Seeders;

use App\Models\GasConsumer;
use App\Models\GasRateSetting;
use Illuminate\Database\Seeder;

/**
 * Confirmed rules #3 and #6: fixed rate ৳330/m³, kg->m³ factor 0.54 by
 * default; the project office is always a gas consumer (is_internal=true)
 * from day one, whether or not it has ever had a meter reading recorded.
 */
class GasSetupSeeder extends Seeder
{
    public function run(): void
    {
        GasRateSetting::firstOrCreate(
            ['effective_from' => now()->startOfYear()->toDateString()],
            ['method' => 'FIXED', 'rate' => 330, 'm3_per_kg' => 0.54, 'minimum_charge' => 0],
        );

        GasConsumer::firstOrCreate(
            ['name' => 'প্রজেক্ট অফিস', 'is_internal' => true],
            ['active' => true],
        );
    }
}
