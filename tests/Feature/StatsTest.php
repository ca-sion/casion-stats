<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\AthleteCategory;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Result;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_stats_can_be_filtered_by_discipline(): void
    {
        $discipline1 = Discipline::factory()->create(['name' => '100m']);
        $discipline2 = Discipline::factory()->create(['name' => '200m']);
        
        Result::factory()->create(['discipline_id' => $discipline1->id]);
        Result::factory()->create(['discipline_id' => $discipline2->id]);

        $results = Result::forDiscipline($discipline1->id)->get();

        $this->assertCount(1, $results);
        $this->assertEquals($discipline1->id, $results->first()->discipline_id);
    }

    public function test_stats_are_sorted_by_normalized_performance(): void
    {
        $discipline = Discipline::factory()->create(['sorting' => 'asc']);
        
        Result::factory()->create([
            'discipline_id' => $discipline->id,
            'performance' => '11.00',
            'performance_normalized' => 11.00
        ]);
        Result::factory()->create([
            'discipline_id' => $discipline->id,
            'performance' => '10.50',
            'performance_normalized' => 10.50
        ]);

        $results = Result::forDiscipline($discipline->id)
            ->orderedByPerformance('asc')
            ->get();

        $this->assertEquals('10.50', $results->first()->performance);
    }

    public function test_best_performance_per_athlete_can_be_extracted(): void
    {
        $athlete = Athlete::factory()->create();
        $discipline = Discipline::factory()->create();
        
        Result::factory()->create([
            'athlete_id' => $athlete->id,
            'discipline_id' => $discipline->id,
            'performance' => '11.00',
            'performance_normalized' => 11.00
        ]);
        Result::factory()->create([
            'athlete_id' => $athlete->id,
            'discipline_id' => $discipline->id,
            'performance' => '10.50',
            'performance_normalized' => 10.50
        ]);

        $results = Result::forDiscipline($discipline->id)
            ->orderedByPerformance('asc')
            ->get()
            ->unique('athlete_id');

        $this->assertCount(1, $results);
        $this->assertEquals('10.50', $results->first()->performance);
    }

    public function test_stats_sorting_handles_time_boundaries(): void
    {
        $discipline = Discipline::factory()->create(['sorting' => 'asc']);
        
        // 59.99s should come before 1:01.05s
        Result::factory()->create([
            'discipline_id' => $discipline->id,
            'performance' => '1:01.05',
            'performance_normalized' => 61.05
        ]);
        Result::factory()->create([
            'discipline_id' => $discipline->id,
            'performance' => '59.99',
            'performance_normalized' => 59.99
        ]);

        $results = Result::forDiscipline($discipline->id)
            ->orderedByPerformance('asc')
            ->get();

        $this->assertEquals('59.99', $results[0]->performance);
        $this->assertEquals('1:01.05', $results[1]->performance);
    }

    public function test_stats_sorting_handles_hour_boundaries(): void
    {
        $discipline = Discipline::factory()->create(['sorting' => 'asc']);
        
        // 0h59:30.00 should come before 1:00:15.00
        Result::factory()->create([
            'discipline_id' => $discipline->id,
            'performance' => '1:00:15.00',
            'performance_normalized' => 3615.00
        ]);
        Result::factory()->create([
            'discipline_id' => $discipline->id,
            'performance' => '0h59:30.00',
            'performance_normalized' => 3570.00
        ]);

        $results = Result::forDiscipline($discipline->id)
            ->orderedByPerformance('asc')
            ->get();

        $this->assertEquals('0h59:30.00', $results[0]->performance);
        $this->assertEquals('1:00:15.00', $results[1]->performance);
    }

    public function test_stats_sorting_handles_dot_boundaries(): void
    {
        $discipline = Discipline::factory()->create(['sorting' => 'asc']);
        
        // 2.54.47 (174.47s) should come after 2:53.00 (173s)
        Result::factory()->create([
            'discipline_id' => $discipline->id,
            'performance' => '2:53.00',
            'performance_normalized' => 173.00
        ]);
        Result::factory()->create([
            'discipline_id' => $discipline->id,
            'performance' => '2.54.47',
            'performance_normalized' => 174.47
        ]);

        $results = Result::forDiscipline($discipline->id)
            ->orderedByPerformance('asc')
            ->get();

        $this->assertEquals('2:53.00', $results[0]->performance);
        $this->assertEquals('2.54.47', $results[1]->performance);
    }

    public function test_stats_sorting_handles_metadata_suffixes(): void
    {
        $discipline = Discipline::factory()->create(['sorting' => 'asc']);
        
        // 16.41 : 200 (16.41s) should come after 16.30 (16.30s)
        Result::factory()->create([
            'discipline_id' => $discipline->id,
            'performance' => '16.41 : 200',
            'performance_normalized' => 16.41
        ]);
        Result::factory()->create([
            'discipline_id' => $discipline->id,
            'performance' => '16.30',
            'performance_normalized' => 16.30
        ]);
        // 54.34-200 (54.34s) should come before 55.00 (55s)
        Result::factory()->create([
            'discipline_id' => $discipline->id,
            'performance' => '54.34-200',
            'performance_normalized' => 54.34
        ]);

        $results = Result::forDiscipline($discipline->id)
            ->orderedByPerformance('asc')
            ->get();

        $this->assertEquals('16.30', $results[0]->performance);
        $this->assertEquals('16.41 : 200', $results[1]->performance);
        $this->assertEquals('54.34-200', $results[2]->performance);
    }

    public function test_stats_sorting_handles_double_dot_boundaries(): void
    {
        $discipline = Discipline::factory()->create(['sorting' => 'asc']);
        
        // 14..13 (14.13s) should come after 14.10 (14.1s)
        Result::factory()->create([
            'discipline_id' => $discipline->id,
            'performance' => '14.10',
            'performance_normalized' => 14.10
        ]);
        Result::factory()->create([
            'discipline_id' => $discipline->id,
            'performance' => '14..13',
            'performance_normalized' => 14.13
        ]);

        $results = Result::forDiscipline($discipline->id)
            ->orderedByPerformance('asc')
            ->get();

        $this->assertEquals('14.10', $results[0]->performance);
        $this->assertEquals('14..13', $results[1]->performance);
    }

    public function test_stats_sorting_handles_non_performance_strings(): void
    {
        $discipline = Discipline::factory()->create(['sorting' => 'asc']);
        
        // p.p. should result in null and not break sorting
        $result = Result::factory()->create([
            'discipline_id' => $discipline->id,
            'performance' => 'p.p.',
            'performance_normalized' => null
        ]);

        $this->assertNull($result->performance_normalized);
    }
}
