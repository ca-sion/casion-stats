<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\AthleteCategory;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Result;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StatsTableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->detectEnvironment(fn() => 'local');
    }

    /**
     * Test the component renders successfully.
     */
    public function test_component_renders(): void
    {
        Discipline::factory()->create(['name' => '100m', 'order' => 1]);

        Livewire::test(\App\Livewire\StatsTable::class)
            ->assertStatus(200)
            ->assertSee('100m');
    }

    /**
     * Test discipline filtering and sorting by order.
     */
    public function test_filters_by_discipline_and_sorts_by_order(): void
    {
        $d2 = Discipline::factory()->create(['name' => '200m', 'order' => 2]);
        $d1 = Discipline::factory()->create(['name' => '100m', 'order' => 1]);

        Livewire::test(\App\Livewire\StatsTable::class)
            ->assertSet('disciplineId', $d1->id) // Should default to first by order
            ->set('disciplineId', $d2->id)
            ->assertSet('disciplineId', $d2->id);
    }

    /**
     * Test diagnostic detection.
     */
    public function test_detects_diagnostics(): void
    {
        $discipline = Discipline::factory()->create(['sorting' => 'asc']);
        $category = AthleteCategory::factory()->create(['genre' => 'm']);
        $athlete = Athlete::factory()->create(['genre' => 'w']); // Mismatch
        $event = Event::factory()->create(['date' => now()]);

        $result = Result::factory()->create([
            'athlete_id' => $athlete->id,
            'athlete_category_id' => $category->id,
            'discipline_id' => $discipline->id,
            'event_id' => $event->id,
            'performance' => '10.50'
        ]);

        Livewire::test(\App\Livewire\StatsTable::class, ['disciplineId' => $discipline->id])
            ->set('fix', true)
            ->assertSee('Genre w ≠ Cat m');
    }

    /**
     * Test bulk fix functionality.
     */
    public function test_bulk_fix_genre_mismatch(): void
    {
        $discipline = Discipline::factory()->create(['sorting' => 'asc']);
        $category = AthleteCategory::factory()->create(['genre' => 'm']);
        $athlete = Athlete::factory()->create(['genre' => 'w']);
        $event = Event::factory()->create(['date' => now()]);

        Result::factory()->create([
            'athlete_id' => $athlete->id,
            'athlete_category_id' => $category->id,
            'discipline_id' => $discipline->id,
            'event_id' => $event->id,
            'performance' => '10.50'
        ]);

        Livewire::test(\App\Livewire\StatsTable::class, ['disciplineId' => $discipline->id])
            ->set('fix', true)
            ->call('bulkFix', ['genre_mismatch'])
            ->assertSet('fix', true); // Simple check since assertHasFlash is not standard

        $this->assertEquals('m', $athlete->fresh()->genre);
    }

    /**
     * Test inclusive category filtering.
     */
    public function test_inclusive_category_filtering(): void
    {
        $discipline = Discipline::factory()->create();
        $catU18 = AthleteCategory::factory()->create(['name' => 'U18 M', 'age_limit' => 17, 'genre' => 'm']);
        $catU16 = AthleteCategory::factory()->create(['name' => 'U16 M', 'age_limit' => 15, 'genre' => 'm']);
        
        $athlete = Athlete::factory()->create(['birthdate' => now()->subYears(14)]); // 14 years old
        $event = Event::factory()->create(['date' => now()]);

        // Result in U16
        Result::factory()->create([
            'athlete_id' => $athlete->id,
            'athlete_category_id' => $catU16->id,
            'discipline_id' => $discipline->id,
            'event_id' => $event->id,
        ]);

        // Filter by U18 inclusive should see U16 results
        Livewire::test(\App\Livewire\StatsTable::class, ['disciplineId' => $discipline->id])
            ->set('categoryId', $catU18->id)
            ->set('inclusiveCategory', true)
            ->assertViewHas('results', function ($results) {
                return $results->count() === 1;
            });
    }
}
