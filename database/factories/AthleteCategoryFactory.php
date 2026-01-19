<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AthleteCategory>
 */
class AthleteCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'age_limit' => $this->faker->numberBetween(10, 99),
            'genre' => $this->faker->randomElement(['m', 'w']),
            'order' => $this->faker->unique()->numberBetween(1, 100),
        ];
    }
}
