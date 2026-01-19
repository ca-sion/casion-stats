<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Result>
 */
class ResultFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'athlete_id' => \App\Models\Athlete::factory(),
            'discipline_id' => \App\Models\Discipline::factory(),
            'event_id' => \App\Models\Event::factory(),
            'athlete_category_id' => \App\Models\AthleteCategory::factory(),
            'performance' => '10.00',
            'performance_normalized' => 10.00,
        ];
    }
}
