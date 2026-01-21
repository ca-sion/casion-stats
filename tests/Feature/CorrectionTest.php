<?php

namespace Tests\Feature;

use App\Livewire\StatsTable;
use App\Models\Athlete;
use App\Models\AthleteCategory;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Result;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_sync_athlete_genre_locally(): void
    {
        // Mock local environment
        $this->app['env'] = 'local';

        $category = AthleteCategory::factory()->create(['genre' => 'w']);
        $athlete = Athlete::factory()->create(['genre' => 'm']);

        Livewire::test(StatsTable::class)
            ->call('syncAthleteGenre', $athlete->id, 'w');

        $this->assertEquals('w', $athlete->fresh()->genre);
    }

    public function test_it_can_delete_duplicate_result_locally(): void
    {
        $this->app['env'] = 'local';

        $result = Result::factory()->create();

        Livewire::test(StatsTable::class)
            ->call('deleteResult', $result->id);

        $this->assertDatabaseMissing('results', ['id' => $result->id]);
    }

    public function test_it_aborts_corrections_if_not_local(): void
    {
        $this->app['env'] = 'production';

        $athlete = Athlete::factory()->create(['genre' => 'm']);

        Livewire::test(StatsTable::class)
            ->call('syncAthleteGenre', $athlete->id, 'w')
            ->assertStatus(403);

        $this->assertEquals('m', $athlete->fresh()->genre);
    }

    public function test_it_can_change_category_locally(): void
    {
        $this->app['env'] = 'local';

        $result = Result::factory()->create();
        $newCategory = AthleteCategory::factory()->create();

        Livewire::test(StatsTable::class)
            ->call('changeCategory', $result->id, $newCategory->id);

        $this->assertEquals($newCategory->id, $result->fresh()->athlete_category_id);
    }

    public function test_it_can_update_performance_locally(): void
    {
        $this->app['env'] = 'local';

        $result = Result::factory()->create(['performance' => 'Invalid!']);

        Livewire::test(StatsTable::class)
            ->call('updatePerformance', $result->id, '10.50');

        $this->assertEquals('10.50', $result->fresh()->performance);
    }

    public function test_it_can_bulk_fix_multiple_issues(): void
    {
        $this->app['env'] = 'local';

        // 1. Genre mismatch
        $catW = AthleteCategory::factory()->create(['genre' => 'w']);
        $athleteM = Athlete::factory()->create(['genre' => 'm']);
        $res1 = Result::factory()->create(['athlete_id' => $athleteM->id, 'athlete_category_id' => $catW->id]);

        // 2. Duplicate
        $athlete = Athlete::factory()->create();
        $event = Event::factory()->create();
        $discipline = Discipline::factory()->create();
        $res2 = Result::factory()->create(['athlete_id' => $athlete->id, 'event_id' => $event->id, 'discipline_id' => $discipline->id]);
        $res3 = Result::factory()->create(['athlete_id' => $athlete->id, 'event_id' => $event->id, 'discipline_id' => $discipline->id]);

        // We need to trigger the bulkFix on the component that sees these results
        // results are filtered by disciplineId in the component
        Livewire::test(StatsTable::class, ['disciplineId' => $discipline->id])
            ->set('fix', true)
            ->call('bulkFix');

        // Check genre sync (different discipline from the one used for duplicates, so we need to test separately or use same discipline)
        // Actually, bulkFix processes ALL current results in the table.
        // My test used a specific disciplineId for duplicates, so $res1 wasn't in the list!

        Livewire::test(StatsTable::class, ['disciplineId' => $res1->discipline_id])
            ->set('fix', true)
            ->call('bulkFix');

        $this->assertEquals('w', $athleteM->fresh()->genre);
        $this->assertEquals(1, Result::where('athlete_id', $athlete->id)->count());
    }
}
