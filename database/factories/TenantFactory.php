<?php

namespace Database\Factories;

use App\Models\Flat;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Tenant>
 */
class TenantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'flat_id' => Flat::factory(),
            'person_id' => Person::factory(),
            'start_date' => now()->subMonths(2)->toDateString(),
            'status' => 'ACTIVE',
        ];
    }
}
