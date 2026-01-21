<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\AthleteCategory;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Result;
use App\Services\HistoricalImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HistoricalImportTest extends TestCase
{
    use RefreshDatabase;

    protected HistoricalImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new HistoricalImportService();
    }

    #[Test]
    public function it_can_parse_csv_file_correctly()
    {
        $path = base_path('resources/data/import-2010-indoor-test.csv');
        
        $data = $this->service->parseCsv($path);

        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        
        // Check first row structure based on 2010 file content
        // Row 1 is usually header, Row 2 is Section header, Row 3 is first data
        // parseCsv skips header and handles sections.
        
        $firstItem = $data[0];
        $this->assertEquals('50m', $firstItem['raw_discipline']);
        $this->assertEquals('Männer', $firstItem['raw_category']);
        $this->assertEquals('Bastien', $firstItem['firstname']);
        $this->assertEquals('Aymon', $firstItem['lastname']);
        $this->assertEquals('1991-01-18', $firstItem['birthdate']);
        $this->assertEquals('121702', $firstItem['license']);
        $this->assertEquals('6.81', $firstItem['performance']);
        // Column 12 is Date. 
        $this->assertEquals('17.01.2010', $firstItem['date']);
    }

    #[Test]
    public function it_can_map_disciplines_and_categories()
    {
        // 1. Create existing to test finding
        Discipline::create(['name_de' => 'Weitsprung', 'name_fr' => 'Longueur', 'type' => 'individual']);
        
        $foundDisp = $this->service->findOrMapDiscipline('Weitsprung');
        $this->assertEquals('Weitsprung', $foundDisp->name_de);
        $this->assertFalse($foundDisp->wasRecentlyCreated);

        // 2. Create new from scratch
        $newDisp = $this->service->findOrMapDiscipline('NewSport');
        $this->assertEquals('NewSport', $newDisp->name_de);
        $this->assertTrue($newDisp->wasRecentlyCreated);

        // 3. Category Mapping
        AthleteCategory::create(['name' => 'U18 M', 'name_de' => 'U18 M']);
        $foundCat = $this->service->findOrMapCategory('U18 M');
        $this->assertEquals('U18 M', $foundCat->name_de);
        
        $newCat = $this->service->findOrMapCategory('NewCat');
        $this->assertEquals('NewCat', $newCat->name_de);
        $this->assertTrue($newCat->wasRecentlyCreated);
    }

    #[Test]
    public function it_can_resolve_athletes_in_various_scenarios()
    {
        // Scenario 1: Exact License Match
        $existing = Athlete::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'birthdate' => '1990-01-01',
            'license' => '123456',
            'genre' => 'm'
        ]);

        $row1 = [
            'license' => '123456',
            'firstname' => 'Johnny', // Different name but same license
            'lastname' => 'Doe',
            'birthdate' => '1990-01-01'
        ];
        
        [$athlete1, $isNew1] = $this->service->resolveAthlete($row1);
        $this->assertEquals($existing->id, $athlete1->id);
        $this->assertFalse($isNew1);

        // Scenario 2: Name + Year Match (Fuzzy)
        // Existing has no license, strict birthdate
        $existing2 = Athlete::create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'birthdate' => '2000-01-01', // Default Jan 1st
            'genre' => 'w'
        ]);
        
        $row2 = [
            'license' => '999999',
            'firstname' => 'Jane',
            'lastname' => 'Smith',
            'birthdate' => '2000-05-20', // Specific date in same year
            'raw_category' => 'Frauen'
        ];

        [$athlete2, $isNew2] = $this->service->resolveAthlete($row2);
        
        $this->assertEquals($existing2->id, $athlete2->id);
        $this->assertFalse($isNew2);
        // Verify enrichment
        $this->assertEquals('2000-05-20', $athlete2->fresh()->birthdate->format('Y-m-d'));
        $this->assertEquals('999999', $athlete2->fresh()->license);

        // Scenario 3: New Athlete
        $row3 = [
            'license' => '',
            'firstname' => 'New',
            'lastname' => 'User',
            'birthdate' => '1995-07-07',
            'raw_category' => 'Männer'
        ];
        
        [$athlete3, $isNew3] = $this->service->resolveAthlete($row3);
        $this->assertTrue($isNew3);
        $this->assertEquals('New', $athlete3->first_name);
        $this->assertDatabaseHas('athletes', ['first_name' => 'New']);
    }

    #[Test]
    public function test_full_import_process_indoor_2010()
    {
        $path = base_path('resources/data/import-2010-indoor-test.csv');
        $data = $this->service->parseCsv($path);

        $this->assertCount(102, $data); // Actual file has 102 rows

        // Limit processing to first 5 for speed in test, or verify all?
        // Let's verify all to be robust as requested "Vriament tester tous les cas"
        // But for unit test speed, creating 100+ items is fine in sqlite memory.
        
        foreach ($data as $row) {
            [$athlete, $isNew] = $this->service->resolveAthlete($row);
            $discipline = $this->service->findOrMapDiscipline($row['raw_discipline']);
            $category = $this->service->findOrMapCategory($row['raw_category']);
            
            $this->service->importResult($row, $athlete, $discipline, $category);
        }

        // Assertions
        $this->assertGreaterThan(3, Athlete::count());
        $this->assertDatabaseCount('results', 102);
        
        // Specific check on Bastien Aymon
        $bastien = Athlete::where('last_name', 'Aymon')->first();
        $this->assertNotNull($bastien);
        $this->assertEquals('Bastien', $bastien->first_name);
        $this->assertEquals('1991-01-18', $bastien->birthdate->format('Y-m-d'));
        
        // Check Result
        $result = Result::where('athlete_id', $bastien->id)->first();
        $this->assertEquals('6.81', $result->performance);
        $this->assertEquals(6.81, $result->performance_normalized);
        $this->assertNotNull($result->iaaf_points);
        $this->assertGreaterThan(0, $result->iaaf_points);
        // $this->assertEquals(1, $result->rank); // Rank is now ignored during import
        
        // Check Event creation
        $this->assertEquals('Championnats romands en salle', $result->event->name);
        $this->assertEquals('2010-01-17', $result->event->date->format('Y-m-d'));
    }

    #[Test]
    public function test_full_import_process_outdoor_2024()
    {
        $path = base_path('resources/data/import-2024-outdoor-test.csv');
        $data = $this->service->parseCsv($path);
        
        // Based on head output: Martin, Benjamin, Léo
        // Actual file has 1038 rows
        $this->assertCount(1038, $data);

        foreach ($data as $row) {
            [$athlete, $isNew] = $this->service->resolveAthlete($row);
            $discipline = $this->service->findOrMapDiscipline($row['raw_discipline']);
            $category = $this->service->findOrMapCategory($row['raw_category']);
            
            $this->service->importResult($row, $athlete, $discipline, $category);
        }

        $this->assertGreaterThan(3, Athlete::count());
        $this->assertDatabaseCount('results', 1038);
        
        // Check Benjamin Savioz (11.34, +1,8 wind, 28.04.2024)
        $benjamin = Athlete::where('first_name', 'Benjamin')->where('last_name', 'Savioz')->first();
        $this->assertNotNull($benjamin);
        // DateOfBirth in CSV is 2009-0-0 -> should be 2009-01-01
        $this->assertEquals('2009-01-01', $benjamin->birthdate->format('Y-m-d'));
        
        $result = Result::where('athlete_id', $benjamin->id)->first();
        $this->assertEquals('11.34', $result->performance);
        $this->assertEquals('+1,8', $result->wind);
        
        // Check Event
        // Date 28.04.2024 -> 2024-04-28
        $this->assertEquals('Meeting d\'ouverture (WRC)', $result->event->name);
        $this->assertEquals('2024-04-28', $result->event->date->format('Y-m-d'));
    }

    #[Test]
    public function test_can_detect_duplicates_with_loose_event_matching()
    {
        // 1. Create Baseline Data
        $athlete = Athlete::create([
            'first_name' => 'Gaelle',
            'last_name' => 'Fumeaux',
            'birthdate' => '1990-01-01',
            'genre' => 'w'
        ]);
        
        $discipline = Discipline::create(['name_fr' => 'Longueur', 'name_de' => 'Weitsprung', 'type' => 'individual']);
        $category = AthleteCategory::create(['name' => 'W', 'age_limit' => 99]);
        
        // Event in DB: "Meeting de Lausanne"
        $event = Event::create([
            'name' => 'Meeting de Lausanne',
            'location' => 'Lausanne',
            'date' => '2001-09-02'
        ]);
        
        Result::create([
            'athlete_id' => $athlete->id,
            'discipline_id' => $discipline->id,
            'event_id' => $event->id,
            'athlete_category_id' => $category->id,
            'performance' => '5.40',
            'rank' => 1
        ]);
        
        // 2. Prepare IMPORT Data (Different Event Name: "Lausanne")
        $row = [
            'firstname' => 'Gaelle',
            'lastname' => 'Fumeaux',
            'birthdate' => '1990-01-01',
            'raw_discipline' => 'Weitsprung',
            'raw_category' => 'W',
            'performance' => '5.40',
            'date' => '02.09.2001', // Same date
            'location' => 'Lausanne',
            'event_name' => 'Lausanne', // DIFFERENT NAME
            'rank' => 1,
            'wind' => null
        ];
        
        // 3. Check Duplicate Status
        // It SHOULD find the duplicate despite different event name
        $exists = $this->service->checkResultExists($row, $athlete, $discipline, $category);
        
        $this->assertTrue($exists, 'Should detect duplicate even if Event Name differs (Lausanne vs Meeting de Lausanne)');
    }

    #[Test]
    public function test_detects_duplicate_with_different_category()
    {
        // 1. Create Baseline Data
        $athlete = Athlete::create([
            'first_name' => 'Sarah',
            'last_name' => 'Atcho',
            'birthdate' => '1995-06-01',
            'genre' => 'w'
        ]);
        
        $discipline = Discipline::create(['name_fr' => '200m', 'name_de' => '200m', 'type' => 'individual']);
        
        // DB has specific category "U23 W"
        $catU23 = AthleteCategory::create(['name' => 'U23 W', 'age_limit' => 22]);
        
        $event = Event::create([
            'name' => 'Meeting de Genève',
            'location' => 'Genève',
            'date' => '2015-06-06'
        ]);
        
        Result::create([
            'athlete_id' => $athlete->id,
            'discipline_id' => $discipline->id,
            'event_id' => $event->id,
            'athlete_category_id' => $catU23->id,
            'performance' => '23.50',
        ]);
        
        // 2. Prepare IMPORT Data (Generic Category: "Frauen")
        $importCat = AthleteCategory::create(['name' => 'W', 'name_de' => 'Frauen']);
        
        $row = [
            'firstname' => 'Sarah',
            'lastname' => 'Atcho',
            'birthdate' => '1995-06-01',
            'raw_discipline' => '200m',
            'raw_category' => 'Frauen', // Different raw category
            'performance' => '23.50',
            'date' => '06.06.2015',
            'location' => 'Genève',
            'event_name' => 'Meeting de Genève',
            'rank' => 1,
            'wind' => null
        ];
        
        // 3. Check Duplicate Status
        // Even though we pass $importCat (W), the query should trigger on existing result ($catU23)
        $exists = $this->service->checkResultExists($row, $athlete, $discipline, $importCat);
        
        $this->assertTrue($exists, 'Should detect duplicate even if Category differs (U23 W vs W)');
    }
    #[Test]
    public function test_detects_duplicate_with_performance_string_mismatch()
    {
        // 1. Baseline: DB has "5.4" (Float-like string)
        $athlete = Athlete::create([
            'first_name' => 'Gaelle',
            'last_name' => 'Fumeaux',
            'birthdate' => '1985-06-25',
            'genre' => 'w'
        ]);
        
        $discipline = Discipline::create(['name_fr' => 'Longueur', 'name_de' => 'Weitsprung', 'type' => 'individual']);
        $category = AthleteCategory::create(['name' => 'U18 W']);
        
        $event = Event::create([
            'name' => 'Lausanne',
            'date' => '2001-09-02', 
            'location' => 'Lausanne'
        ]);
        
        Result::create([
            'athlete_id' => $athlete->id,
            'discipline_id' => $discipline->id,
            'event_id' => $event->id,
            'athlete_category_id' => $category->id,
            'performance' => '5.4', // DB has simplified string
            'performance_normalized' => 5.40
        ]);
        
        // 2. Import: CSV has "5.40" (Formatted string)
        $row = [
            'firstname' => 'Gaelle',
            'lastname' => 'Fumeaux',
            'birthdate' => '1985-06-25',
            'raw_discipline' => 'Weitsprung',
            'raw_category' => 'U18 W',
            'performance' => '5.40', // Imports as "5.40"
            'date' => '02.09.2001',
            'location' => 'Lausanne',
            'event_name' => 'Lausanne',
            'rank' => 1,
            'wind' => null
        ];
        
        // 3. Check: Should confirm it exists
        $exists = $this->service->checkResultExists($row, $athlete, $discipline, $category);
        
        $this->assertTrue($exists, 'Should detect duplicate even if Performance string differs (5.4 vs 5.40)');
    }

    #[Test]
    public function test_it_can_resolve_events_with_fuzzy_matching()
    {
        // 1. Setup existing competition
        $date = '2024-05-15';
        $location = 'Geneva';
        $existingEvent = Event::create([
            'name' => 'Meeting International de Genève',
            'date' => $date,
            'location' => $location
        ]);

        $athlete = Athlete::factory()->create();
        $discipline = Discipline::create(['name_de' => '100m', 'name_fr' => '100m', 'type' => 'individual']);
        $category = AthleteCategory::create(['name' => 'M', 'name_de' => 'Männer']);

        // 2. Import with slightly different name
        $data = [
            'event_name' => 'Meeting de Genève', // Similar but not exact
            'date' => '15.05.2024',
            'location' => $location,
            'performance' => '10.50',
            'wind' => '+0.1',
        ];

        $result = $this->service->importResult($data, $athlete, $discipline, $category);

        // Assertions: Should use the existing event
        $this->assertEquals($existingEvent->id, $result->event_id);
        $this->assertEquals(1, Event::count());

        // 3. Import with SAME name but DIFFERENT location -> Should create NEW event
        $dataDifferentLocation = [
            'event_name' => 'Meeting International de Genève',
            'date' => '15.05.2024',
            'location' => 'Lausanne', // Different
            'performance' => '10.60',
            'wind' => '+0.1',
        ];

        $result2 = $this->service->importResult($dataDifferentLocation, $athlete, $discipline, $category);
        $this->assertNotEquals($existingEvent->id, $result2->event_id);
        $this->assertEquals(2, Event::count());

        // 4. Import with SAME name but DIFFERENT date -> Should create NEW event
        $dataDifferentDate = [
            'event_name' => 'Meeting International de Genève',
            'date' => '16.05.2024', // Different
            'location' => $location,
            'performance' => '10.70',
            'wind' => '+0.1',
        ];

        $result3 = $this->service->importResult($dataDifferentDate, $athlete, $discipline, $category);
        $this->assertNotEquals($existingEvent->id, $result3->event_id);
        $this->assertEquals(3, Event::count());
    }
}
