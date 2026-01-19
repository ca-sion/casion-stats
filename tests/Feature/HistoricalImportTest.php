<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\AthleteCategory;
use App\Models\Discipline;
use App\Services\HistoricalImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoricalImportTest extends TestCase
{
    use RefreshDatabase;

    private HistoricalImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new HistoricalImportService();
        
        // Seed some basic data
        Discipline::create(['name_fr' => '50 m', 'name_de' => '50m']);
        Discipline::create(['name_fr' => 'Longueur']); // No german name
        
        AthleteCategory::create(['name' => 'MAN', 'name_de' => 'Männer', 'genre' => 'm']);
        AthleteCategory::create(['name' => 'U10 M', 'genre' => 'm', 'age_limit' => 9]);
    }

    public function test_it_parses_csv_correctly()
    {
        // Mock CSV content
        $csvContent = <<<CSV
Id,Firstname,Lastname,Infix,DateOfBirth,License,Nation,Yob,OrganizationName,BestResultString,BestWindString,Rank,PerformanceDateTime,Town,CompetitionName,CompetitionNation,RelayMemberDetails,MultiDetails,Area,District,Region
#50m #Männer
236e54aa,Benjamin,Savioz,,2009-0-0,260071,SUI,2009,CA Sion,6.55,,1,05.10.2025,Sion,Meeting des Familles,SUI,,,,,Valais
CSV;
        $path = sys_get_temp_dir() . '/test_import.csv';
        file_put_contents($path, $csvContent);

        $parsed = $this->service->parseCsv($path);

        $this->assertCount(1, $parsed);
        $this->assertEquals('Benjamin', $parsed[0]['firstname']);
        $this->assertEquals('50m', $parsed[0]['raw_discipline']);
        $this->assertEquals('Männer', $parsed[0]['raw_category']);
        $this->assertEquals('2009-01-01', $parsed[0]['birthdate']);
        $this->assertEquals('05.10.2025', $parsed[0]['date']);
        
        unlink($path);
    }

    public function test_it_recognizes_mapped_discipline()
    {
        $d = $this->service->findOrMapDiscipline('50m');
        $this->assertNotNull($d);
        $this->assertEquals('50 m', $d->name_fr);
    }

    public function test_it_returns_null_for_unmapped_discipline()
    {
        $d = $this->service->findOrMapDiscipline('UnknownSport');
        $this->assertNull($d);
    }

    public function test_it_resolves_existing_athlete_by_license()
    {
        $existing = Athlete::create([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'birthdate' => '2000-01-01',
            'license' => '123456',
            'genre' => 'm'
        ]);

        $data = [
            'firstname' => 'Jean',
            'lastname' => 'Dupont', // Name could be different
            'license' => '123456',
            'birthdate' => '2000-01-01'
        ];

        [$athlete, $isNew] = $this->service->resolveAthlete($data);

        $this->assertFalse($isNew);
        $this->assertEquals($existing->id, $athlete->id);
    }

    public function test_it_resolves_existing_athlete_by_name_and_updates_license()
    {
        $existing = Athlete::create([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'birthdate' => '2000-05-20',
            'genre' => 'm'
            // No license
        ]);

        $data = [
            'firstname' => 'Jean',
            'lastname' => 'Dupont',
            'birthdate' => '2000-01-01', // Date is slightly off/incomplete in CSV usually
            'license' => '999999'
        ];

        [$athlete, $isNew] = $this->service->resolveAthlete($data);

        $this->assertFalse($isNew);
        $this->assertEquals('999999', $athlete->license);
    }

    public function test_it_creates_new_athlete()
    {
        $data = [
            'firstname' => 'New',
            'lastname' => 'Guy',
            'birthdate' => '1990-01-01',
            'license' => '888888'
        ];

        [$athlete, $isNew] = $this->service->resolveAthlete($data);

        $this->assertTrue($isNew);
        $this->assertDatabaseHas('athletes', ['license' => '888888']);
    }
}
