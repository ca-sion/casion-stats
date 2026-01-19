<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\Result;
use App\Services\AthleteDeduplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AthleteDeduplicationTest extends TestCase
{
    use RefreshDatabase;

    protected AthleteDeduplicationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AthleteDeduplicationService();
    }

    public function test_it_detects_exact_duplicates()
    {
        $a1 = Athlete::factory()->create(['first_name' => 'John', 'last_name' => 'Doe', 'birthdate' => '2000-01-01']);
        $a2 = Athlete::factory()->create(['first_name' => 'John', 'last_name' => 'Doe', 'birthdate' => '2000-01-01']);
        
        $duplicates = $this->service->findDuplicates();
        
        $this->assertCount(1, $duplicates);
        $this->assertCount(2, $duplicates[0]);
    }

    public function test_it_detects_fuzzy_duplicates_with_same_birth_year()
    {
        // "Jon Doe" vs "John Doe" (Typo)
        $a1 = Athlete::factory()->create(['first_name' => 'John', 'last_name' => 'Doe', 'birthdate' => '2000-05-20']);
        $a2 = Athlete::factory()->create(['first_name' => 'Jon', 'last_name' => 'Doe', 'birthdate' => '2000-01-01']); // Same year
        
        $duplicates = $this->service->findDuplicates();
        
        $this->assertCount(1, $duplicates);
    }

    public function test_it_detects_flipped_names()
    {
        $a1 = Athlete::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);
        $a2 = Athlete::factory()->create(['first_name' => 'Doe', 'last_name' => 'John']);
        
        $duplicates = $this->service->findDuplicates();
        
        $this->assertCount(1, $duplicates);
    }

    public function test_it_ignores_distinct_athletes()
    {
        Athlete::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);
        Athlete::factory()->create(['first_name' => 'Jane', 'last_name' => 'Doe']); // Different first name
        
        $duplicates = $this->service->findDuplicates();
        
        $this->assertCount(0, $duplicates);
    }

    public function test_it_merges_athletes_correctly()
    {
        $primary = Athlete::factory()->create([
            'first_name' => 'John', 
            'last_name' => 'Doe', 
            'license' => '12345',
            'birthdate' => null
        ]);
        
        $secondary = Athlete::factory()->create([
            'first_name' => 'John', 
            'last_name' => 'Doe', 
            'license' => null, // Should keep primary
            'birthdate' => '2000-01-01' // Should fill primary
        ]);

        // Create results for secondary
        Result::factory()->create(['athlete_id' => $secondary->id]);
        Result::factory()->create(['athlete_id' => $secondary->id]);

        $this->service->mergeAthletes($primary, $secondary);

        // Verify Primary
        $primary->refresh();
        $this->assertEquals('12345', $primary->license);
        $this->assertEquals('2000-01-01', $primary->birthdate->format('Y-m-d'));
        $this->assertEquals(2, $primary->results()->count());

        // Verify Secondary is deleted
        $this->assertDatabaseMissing('athletes', ['id' => $secondary->id]);
    }
}
