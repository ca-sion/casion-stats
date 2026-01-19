<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\AthleteCategory;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Result;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiagnosticTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_detects_genre_mismatch(): void
    {
        $category = AthleteCategory::factory()->create(['genre' => 'w']); // Female category
        $athlete = Athlete::factory()->create(['genre' => 'm']); // Male athlete
        $result = Result::factory()->create([
            'athlete_id' => $athlete->id,
            'athlete_category_id' => $category->id,
        ]);

        $diagnostics = $result->getDiagnostics();

        $this->assertCount(1, collect($diagnostics)->where('type', 'genre_mismatch'));
    }
    public function test_it_detects_age_mismatch_limit(): void
    {
        $category = AthleteCategory::factory()->create(['name' => 'U18 M', 'age_limit' => 17, 'genre' => 'm']);
        // Born in 2000, event in 2018 -> 18 years old (Athletic Age)
        $athlete = Athlete::factory()->create(['birthdate' => '2000-12-31', 'genre' => 'm']);
        $event = Event::factory()->create(['date' => '2018-01-01']);
        
        $result = Result::factory()->create([
            'athlete_id' => $athlete->id,
            'athlete_category_id' => $category->id,
            'event_id' => $event->id,
        ]);

        $diagnostics = collect($result->getDiagnostics());
        $ageIssue = $diagnostics->firstWhere('type', 'age_mismatch');

        $this->assertNotNull($ageIssue);
        $this->assertStringContainsString('> Limite 17', $ageIssue['label']);
    }

    public function test_it_detects_exact_age_mismatch(): void
    {
        $category = AthleteCategory::factory()->create(['name' => 'U10 W08', 'age_limit' => 8, 'genre' => 'w']);
        
        // Born in 2015, event in 2024 -> 9 years old (Athletic Age)
        // Should be exactly 8
        $athlete = Athlete::factory()->create(['birthdate' => '2015-06-01', 'genre' => 'w']);
        $event = Event::factory()->create(['date' => '2024-06-01']);
        
        $result = Result::factory()->create([
            'athlete_id' => $athlete->id,
            'athlete_category_id' => $category->id,
            'event_id' => $event->id,
        ]);

        $diagnostics = collect($result->getDiagnostics());
        $ageIssue = $diagnostics->firstWhere('type', 'age_mismatch');

        $this->assertNotNull($ageIssue);
        $this->assertStringContainsString('≠ 8 attendu', $ageIssue['label']);
    }


    public function test_it_accepts_correct_athletic_age(): void
    {
        $category = AthleteCategory::factory()->create(['name' => 'U18 M', 'age_limit' => 17]);
        // Born in 2007, event in 2024 -> 17 years old
        $athlete = Athlete::factory()->create(['birthdate' => '2007-12-31']);
        $event = Event::factory()->create(['date' => '2024-01-01']);
        
        $result = Result::factory()->create([
            'athlete_id' => $athlete->id,
            'athlete_category_id' => $category->id,
            'event_id' => $event->id,
        ]);

        $diagnostics = $result->getDiagnostics();
        $this->assertCount(0, collect($diagnostics)->where('type', 'age_mismatch'));
    }

    public function test_it_detects_potential_duplicates(): void
    {
        $athlete = Athlete::factory()->create();
        $discipline = Discipline::factory()->create();
        $event = Event::factory()->create(['date' => '2024-01-01']);
        
        $result1 = Result::factory()->create([
            'athlete_id' => $athlete->id,
            'discipline_id' => $discipline->id,
            'event_id' => $event->id,
        ]);

        $result2 = Result::factory()->create([
            'athlete_id' => $athlete->id,
            'discipline_id' => $discipline->id,
            'event_id' => $event->id,
        ]);

        $diagnostics = $result2->getDiagnostics();

        $this->assertCount(1, collect($diagnostics)->where('type', 'duplicate'));
    }

    public function test_it_detects_format_issues(): void
    {
        $result = Result::factory()->create([
            'performance' => 'Invalid Format!',
        ]);

        $diagnostics = $result->getDiagnostics();

        $this->assertCount(1, collect($diagnostics)->where('type', 'format_issue'));
    }

    public function test_it_returns_no_issues_for_clean_data(): void
    {
        $category = AthleteCategory::factory()->create(['genre' => 'm', 'age_limit' => 20]);
        $athlete = Athlete::factory()->create(['genre' => 'm', 'birthdate' => now()->subYears(15)]);
        $event = Event::factory()->create(['date' => now()]);
        
        $result = Result::factory()->create([
            'athlete_id' => $athlete->id,
            'athlete_category_id' => $category->id,
            'event_id' => $event->id,
            'performance' => '10.50',
        ]);

        $diagnostics = $result->getDiagnostics();

        $this->assertEmpty($diagnostics);
    }
    public function test_it_detects_missing_birthdate(): void
    {
        $athlete = Athlete::factory()->create(['birthdate' => '-0001-11-30']);
        $result = Result::factory()->create(['athlete_id' => $athlete->id]);

        $diagnostics = collect($result->getDiagnostics());

        $this->assertCount(1, $diagnostics->where('type', 'missing_birthdate'));
        $this->assertCount(0, $diagnostics->where('type', 'age_mismatch'));
    }

    public function test_it_detects_suboptimal_senior_category(): void
    {
        $youthCat = AthleteCategory::factory()->create(['name' => 'U18 M', 'age_limit' => 17, 'genre' => 'm']);
        $seniorCat = AthleteCategory::factory()->create(['name' => 'MAN', 'age_limit' => 99, 'genre' => 'm']);
        
        // Born in 2007, event in 2024 -> 17 years old (Could be in U18 M)
        $athlete = Athlete::factory()->create(['birthdate' => '2007-06-01', 'genre' => 'm']);
        $event = Event::factory()->create(['date' => '2024-06-01']);
        
        $result = Result::factory()->create([
            'athlete_id' => $athlete->id,
            'athlete_category_id' => $seniorCat->id,
            'event_id' => $event->id,
        ]);

        $diagnostics = collect($result->getDiagnostics());
        $issue = $diagnostics->firstWhere('type', 'age_mismatch');

        $this->assertNotNull($issue);
        $this->assertStringContainsString('Cat MAN alors que U18 M possible', $issue['label']);
        $this->assertEquals($youthCat->id, $issue['suggested_category_id']);
    }

    public function test_it_prioritizes_general_categories(): void
    {
        // Create both a specific and a general category
        $generalCat = AthleteCategory::factory()->create(['name' => 'U12 W', 'age_limit' => 11, 'genre' => 'w']);
        $specificCat = AthleteCategory::factory()->create(['name' => 'U12 W11', 'age_limit' => 11, 'genre' => 'w']);
        
        // Athlete aged 11
        $athlete = Athlete::factory()->create(['birthdate' => '2013-06-01', 'genre' => 'w']);
        $event = Event::factory()->create(['date' => '2024-06-01']);
        
        // Result currently in a wrong category (e.g., U14 W)
        $wrongCat = AthleteCategory::factory()->create(['name' => 'U14 W', 'age_limit' => 13, 'genre' => 'w']);
        $result = Result::factory()->create([
            'athlete_id' => $athlete->id,
            'athlete_category_id' => $wrongCat->id,
            'event_id' => $event->id,
        ]);

        $diagnostics = collect($result->getDiagnostics());
        $issue = $diagnostics->firstWhere('type', 'age_mismatch');

        $this->assertNotNull($issue);
        // Should suggest the general category because it doesn't end with 2 digits
        $this->assertEquals($generalCat->id, $issue['suggested_category_id']);
    }

    public function test_it_does_not_suggest_specific_category_if_general_is_correct(): void
    {
        $generalCat = AthleteCategory::factory()->create(['name' => 'U14 M', 'age_limit' => 13, 'genre' => 'm']);
        $specificCat = AthleteCategory::factory()->create(['name' => 'U14 M12', 'age_limit' => 12, 'genre' => 'm']);
        
        // Athlete aged 12 (Fits in both, but General is preferred/correct)
        $athlete = Athlete::factory()->create(['birthdate' => '2012-06-01', 'genre' => 'm']);
        $event = Event::factory()->create(['date' => '2024-06-01']);
        
        $result = Result::factory()->create([
            'athlete_id' => $athlete->id,
            'athlete_category_id' => $generalCat->id,
            'event_id' => $event->id,
        ]);

        $diagnostics = collect($result->getDiagnostics());
        $issue = $diagnostics->firstWhere('type', 'age_mismatch');

        // Should NOT have an issue if already in the correct general category
        $this->assertNull($issue, "Should not suggest optimizing to a specific category if already in a correct general one.");
    }
}
