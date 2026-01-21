<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\AthleteCategory;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Result;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResultAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_automatically_normalizes_performance_when_creating(): void
    {
        $discipline = Discipline::factory()->create();
        $athlete = Athlete::factory()->create();
        $event = Event::factory()->create();
        $category = AthleteCategory::factory()->create();

        $result = Result::create([
            'athlete_id' => $athlete->id,
            'discipline_id' => $discipline->id,
            'event_id' => $event->id,
            'athlete_category_id' => $category->id,
            'performance' => '10.50',
        ]);

        $this->assertEquals(10.50, $result->performance_normalized);
    }

    public function test_it_automatically_calculates_points_when_creating(): void
    {
        // 100m Men, 10.00s should be 1206 points (2022 table)
        $discipline = Discipline::factory()->create(['wa_code' => '100', 'name_fr' => '100 m']);
        $athlete = Athlete::factory()->create();
        $event = Event::factory()->create();
        $category = AthleteCategory::factory()->create(['genre' => 'm']);

        $result = Result::create([
            'athlete_id' => $athlete->id,
            'discipline_id' => $discipline->id,
            'event_id' => $event->id,
            'athlete_category_id' => $category->id,
            'performance' => '10.00',
        ]);

        $this->assertEquals(1206, $result->iaaf_points);
    }

    public function test_it_updates_points_when_performance_changes(): void
    {
        $discipline = Discipline::factory()->create(['wa_code' => '100', 'name_fr' => '100 m']);
        $category = AthleteCategory::factory()->create(['genre' => 'm']);

        $result = Result::factory()->create([
            'discipline_id' => $discipline->id,
            'performance' => '10.00',
            'athlete_category_id' => $category->id,
        ]);

        $this->assertEquals(1206, $result->iaaf_points);

        $result->performance = '9.58'; // Bolt WR
        $result->save();

        $this->assertGreaterThan(1206, $result->iaaf_points);
        $this->assertEquals(9.58, $result->performance_normalized);
    }

    public function test_it_updates_points_when_discipline_changes(): void
    {
        $discipline100 = Discipline::factory()->create(['wa_code' => '100', 'name_fr' => '100 m']);
        $discipline200 = Discipline::factory()->create(['wa_code' => '200', 'name_fr' => '200 m']);
        $category = AthleteCategory::factory()->create(['genre' => 'm']);

        $result = Result::factory()->create([
            'discipline_id' => $discipline100->id,
            'performance' => '11.00',
            'athlete_category_id' => $category->id,
        ]);

        $initialPoints = $result->iaaf_points;

        $result->discipline_id = $discipline200->id;
        $result->save();

        $this->assertNotEquals($initialPoints, $result->iaaf_points);
    }
}
