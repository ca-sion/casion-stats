<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\AthleteCategory;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Result;
use App\Livewire\StatsTable;
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
}
