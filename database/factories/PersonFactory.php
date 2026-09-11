<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Person>
 */
class PersonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name_bn' => fake()->name(),
            'mobile' => fake()->unique()->numerify('01#########'),
            'preferred_channel' => 'SMS',
        ];
    }
}
