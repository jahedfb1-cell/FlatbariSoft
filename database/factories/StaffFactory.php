<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Staff>
 */
class StaffFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'designation' => fake()->randomElement(['ম্যানেজার', 'সিকিউরিটি গার্ড', 'ক্লিনার']),
            'mobile' => fake()->unique()->numerify('01#########'),
            'join_date' => now()->subYear()->toDateString(),
            'monthly_salary' => fake()->randomElement([12000, 15000, 20000]),
            'status' => 'ACTIVE',
        ];
    }
}
