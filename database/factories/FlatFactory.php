<?php

namespace Database\Factories;

use App\Models\Building;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Flat>
 */
class FlatFactory extends Factory
{
    public function definition(): array
    {
        return [
            'building_id' => Building::factory(),
            'flat_no' => fake()->unique()->numerify('##'),
            'floor' => (string) fake()->numberBetween(1, 9),
            'size_sqft' => fake()->randomFloat(2, 800, 2000),
            'occupancy_status' => 'EMPTY',
            'status' => 'ACTIVE',
        ];
    }
}
