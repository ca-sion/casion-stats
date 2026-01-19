<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\AthleteCategory;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Result;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InclusiveFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_filters_strictly_by_default(): void
    {
        $u16 = AthleteCategory::factory()->create(['name' => 'U16 M', 'age_limit' => 15, 'genre' => 'm']);
        $u14 = AthleteCategory::factory()->create(['name' => 'U14 M', 'age_limit' => 13, 'genre' => 'm']);
        
        $res16 = Result::factory()->create(['athlete_category_id' => $u16->id]);
        $res14 = Result::factory()->create(['athlete_category_id' => $u14->id]);

        $results = Result::forCategory($u16->id, false)->get();

        $this->assertCount(1, $results);
        $this->assertEquals($u16->id, $results->first()->athlete_category_id);
    }

    public function test_it_filters_inclusively_when_requested(): void
    {
        $u16 = AthleteCategory::factory()->create(['name' => 'U16 M', 'age_limit' => 15, 'genre' => 'm']);
        $u14 = AthleteCategory::factory()->create(['name' => 'U14 M', 'age_limit' => 13, 'genre' => 'm']);
        $u18 = AthleteCategory::factory()->create(['name' => 'U18 M', 'age_limit' => 17, 'genre' => 'm']);
        
        $res16 = Result::factory()->create(['athlete_category_id' => $u16->id]);
        $res14 = Result::factory()->create(['athlete_category_id' => $u14->id]);
        $res18 = Result::factory()->create(['athlete_category_id' => $u18->id]);

        $results = Result::forCategory($u16->id, true)->get();

        $this->assertCount(2, $results);
        $this->assertTrue($results->contains($res16));
        $this->assertTrue($results->contains($res14));
        $this->assertFalse($results->contains($res18));
    }

    public function test_inclusive_filtering_respects_genre(): void
    {
        $u16m = AthleteCategory::factory()->create(['name' => 'U16 M', 'age_limit' => 15, 'genre' => 'm']);
        $u14m = AthleteCategory::factory()->create(['name' => 'U14 M', 'age_limit' => 13, 'genre' => 'm']);
        $u14w = AthleteCategory::factory()->create(['name' => 'U14 W', 'age_limit' => 13, 'genre' => 'w']);
        
        $res16m = Result::factory()->create(['athlete_category_id' => $u16m->id]);
        $res14m = Result::factory()->create(['athlete_category_id' => $u14m->id]);
        $res14w = Result::factory()->create(['athlete_category_id' => $u14w->id]);

        $results = Result::forCategory($u16m->id, true)->get();

        $this->assertCount(2, $results);
        $this->assertTrue($results->contains($res16m));
        $this->assertTrue($results->contains($res14m));
        $this->assertFalse($results->contains($res14w));
    }
}
