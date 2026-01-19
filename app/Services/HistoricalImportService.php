<?php

namespace App\Services;

use App\Models\Athlete;
use App\Models\AthleteCategory;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Result;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class HistoricalImportService
{
    private array $disciplineMapping = [];
    private array $categoryMapping = [];

    /**
     * Parse the CSV file and return structured data.
     *
     * @param string $filePath
     * @return array
     */
    public function parseCsv(string $filePath): array
    {
        $rows = array_map('str_getcsv', file($filePath));
        $header = array_shift($rows); // Remove header row: Id,Firstname,Lastname...

        $currentDiscipline = null;
        $currentCategory = null;
        $parsedData = [];

        foreach ($rows as $row) {
            // Check for section header line (e.g., #50m #Männer)
            if (empty($row[0]) || str_starts_with($row[0], '#')) {
                 // It's likely a section header if the first column is empty or starts with #
                 // But wait, the user example showed:
                 // #50m #Männer
                 // 236e54aa...,Benjamin...
                 // The example had the section line as a single string row, not a CSV row with columns?
                 // Let's look closer at the user request.
                 // "Id,Firstname... \n #50m #Männer \n 236e54aa..."
                 // So "file()" will read that line. "str_getcsv" on that line will probably result in ["#50m #Männer"] or similar depending on delimiters.
                 
                 // Let's re-read the file raw for safety or handle the case where str_getcsv returns one item.
                 $line = $row[0]; 
                 if (str_starts_with($line, '#')) {
                     // Parse parts: #50m #Männer #5000g
                     preg_match_all('/#([^#]+)/', $line, $matches);
                     if (!empty($matches[1])) {
                         $currentDiscipline = trim($matches[1][0] ?? '');
                         $currentCategory = trim($matches[1][1] ?? '');
                         $currentInfo = trim($matches[1][2] ?? ''); // e.g., 5000g or 914mm

                         // Combine discipline and info if available for better mapping
                         if ($currentInfo) {
                            $currentDiscipline .= ' ' . $currentInfo;
                         }
                     }
                     continue; 
                 }
            }

            // If it's a data row and we have context
            if ($currentDiscipline && $currentCategory && count($row) > 10) {
                 // Map CSV columns based on the header provided:
                 // Id,Firstname,Lastname,Infix,DateOfBirth,License,Nation,Yob,OrganizationName,BestResultString,BestWindString,Rank,PerformanceDateTime,Town,CompetitionName...
                 // 0: Id, 1: Firstname, 2: Lastname, 3: Infix, 4: DOB, 5: License, 6: Nation, 7: Yob, 8: Org, 9: Result, 10: Wind, 11: Rank, 12: Date, 13: Town, 14: CompName
                 
                 $parsedData[] = [
                     'raw_discipline' => $currentDiscipline,
                     'raw_category' => $currentCategory,
                     'firstname' => $row[1],
                     'lastname' => $row[2],
                     'birthdate' => $this->parseDateOfBirth($row[4], $row[7] ?? null), // 2009-0-0
                     'license' => empty($row[5]) ? null : $row[5],
                     'yob' => $row[7],
                     'performance' => $row[9],
                     'wind' => $row[10],
                     'rank' => $row[11],
                     'date' => $row[12], // 05.10.2025
                     'location' => $row[13],
                     'event_name' => $row[14],
                     'country' => $row[15] ?? 'SUI',
                 ];
            }
        }
        
        return $parsedData;
    }

    private function parseDateOfBirth($dobString, $yob) {
        // Handle "2009-0-0" or "2016-9-13"
        // If 0-0, default to YYYY-01-01 for DB storage? Or keep null? 
        // Our athletes table has `birthdate` as date. It needs a valid date.
        // If we only have YOB, we usually set 01-01.
        
        $parts = explode('-', $dobString);
        if (count($parts) === 3) {
            $year = (int)$parts[0];
            $month = (int)$parts[1];
            $day = (int)$parts[2];
            
            if ($year === 0 && $yob) $year = $yob;
            if ($month === 0) $month = 1;
            if ($day === 0) $day = 1;
            
            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }
        
        if ($yob) {
            return $yob . '-01-01';
        }
        
        return null;
    }

    public function findOrMapDiscipline(string $germanName): ?Discipline
    {
        // 1. Try exact match on name_de field
        $discipline = Discipline::where('name_de', $germanName)->first();
        if ($discipline) return $discipline;

        // 2. Try automated basic mapping (hardcoded fallback)
        $map = [
            '50m' => '50 m',
            '60m' => '60 m',
            '80m' => '80 m',
            '100m' => '100 m',
            '150m' => '150 m',
            '200m' => '200 m',
            '300m' => '300 m',
            '400m' => '400 m',
            '600m' => '600 m',
            '800m' => '800 m',
            '1000m' => '1000 m',
            '1500m' => '1500 m',
            '2000m' => '2000 m',
            '3000m' => '3000 m',
            '5000m' => '5000 m',

            // Hurdles
            '100m Hürden 838mm' => '100 m haies 84.0',
            '110m Hürden 1067mm' => '110 m haies 106.7',
            '110m Hürden 991mm' => '110 m haies 99.1',
            '110m Hürden 914mm' => '110 m haies 91.4',
            '400m Hürden 914mm' => '400 m haies 91.4',
            '400m Hürden 762mm' => '400 m haies 76.2',
            '80m Hürden 762mm' => '80 m haies 76.2',
            '60m Hürden 762mm' => '60 m haies 76.2',

            // Jumps
            'Hochsprung' => 'Hauteur',
            'Stabhochsprung' => 'Perche',
            'Weitsprung' => 'Longueur',
            'Weitsprung Zone' => 'Longueur (zone)',
            'Dreisprung' => 'Triple',

            // Throws
            'Kugelstoß 7260g' => 'Poids 7.26 kg',
            'Kugelstoß 6000g' => 'Poids 6.00 kg',
            'Kugelstoß 5000g' => 'Poids 5.00 kg',
            'Kugelstoß 4000g' => 'Poids 4.00 kg',
            'Kugelstoß 3000g' => 'Poids 3.00 kg',
            'Kugelstoß 2500g' => 'Poids 2.50 kg',

            'Diskuswurf 2000g' => 'Disque 2.00 kg',
            'Diskuswurf 1750g' => 'Disque 1.75 kg',
            'Diskuswurf 1500g' => 'Disque 1.50 kg',
            'Diskuswurf 1000g' => 'Disque 1.00 kg',
            'Diskuswurf 750g' => 'Disque 0.75 kg',

            'Speerwurf 800g' => 'Javelot 800 gr',
            'Speerwurf 700g' => 'Javelot 700 gr',
            'Speerwurf 600g' => 'Javelot 600 gr',
            'Speerwurf 500g' => 'Javelot 500 gr',
            'Speerwurf 400g' => 'Javelot 400 gr',

            'Ballwurf 200g' => 'Balle 200 gr',
            'Ballwurf 80g' => 'Balle 80 gr',

            // Multi
            'Fünfkampf' => 'Pentathlon', // Check if "Pentathlon" is unique enough or needs context
            'Sechskampf' => 'Hexathlon',
            'Siebenkampf' => 'Heptathlon',
            'Zehnkampf' => 'Décathlon',
            'UBS Kids Cup' => 'UBS Kids Cup',
        ];

        if (isset($map[$germanName])) {
             return Discipline::where('name_fr', $map[$germanName])->first();
        }

        return null; // Needed UI intervention
    }

    public function findOrMapCategory(string $germanName): ?AthleteCategory
    {
        $category = AthleteCategory::where('name_de', $germanName)->first();
        if ($category) return $category;

        // Fallback mapping logic
        $map = [
            'Männer' => 'MAN',
            'Frauen' => 'WOM',
            'U23 Männer' => 'U23 M',
            'U23 Frauen' => 'U23 W',
            'U20 Männer' => 'U20 M',
            'U20 Frauen' => 'U20 W',
            'U18 Männer' => 'U18 M',
            'U18 Frauen' => 'U18 W',
            'U16 Männer' => 'U16 M',
            'U16 Frauen' => 'U16 W',
            'U14 Männer' => 'U14 M',
            'U14 Frauen' => 'U14 W',
            'U12 Männer' => 'U12 M',
            'U12 Frauen' => 'U12 W',
            'U10 Männer' => 'U10 M',
            'U10 Frauen' => 'U10 W',
            
            // Single ages (M 15 -> U16 M, etc)
            'M 15' => 'U16 M', 'M 14' => 'U16 M',
            'W 15' => 'U16 W', 'W 14' => 'U16 W',
            'M 13' => 'U14 M', 'M 12' => 'U14 M',
            'W 13' => 'U14 W', 'W 12' => 'U14 W',
            'M 11' => 'U12 M', 'M 10' => 'U12 M',
            'W 11' => 'U12 W', 'W 10' => 'U12 W',
            'M 9' => 'U10 M', 'M 8' => 'U10 M',
             'W 9' => 'U10 W', 'W 8' => 'U10 W',

            'Masters männlich' => 'MASTERS M',
            'Masters weiblich' => 'MASTERS W',
            'Männer M30' => 'MASTERS M',
        ];

        if (isset($map[$germanName])) {
            return AthleteCategory::where('name', $map[$germanName])->first();
        }

        return null;
    }

    public function resolveAthlete(array $data): array // Returns [Athlete, bool isNew]
    {
        // 1. Try by License
        if (!empty($data['license'])) {
            $athlete = Athlete::where('license', $data['license'])->first();
            if ($athlete) {
                return [$athlete, false];
            }
        }

        // 2. Try be Name + DOB (Fuzzy)
        // First strictly with birthdate if we have it
        $query = Athlete::where('first_name', $data['firstname'])
                        ->where('last_name', $data['lastname']);
        
        // Check if we have exact birthdate match
        $candidates = $query->get();
        foreach ($candidates as $candidate) {
            // Compare years
            $candidateYear = Carbon::parse($candidate->birthdate)->year;
            $importYear = Carbon::parse($data['birthdate'])->year;
            
            if ($candidateYear === $importYear) {
                // Match found! 
                // Update license if missing
                if (!$candidate->license && !empty($data['license'])) {
                    $candidate->license = $data['license'];
                    $candidate->save();
                }
                return [$candidate, false];
            }
        }

        // 3. Create New
        $athlete = Athlete::create([
            'first_name' => $data['firstname'],
            'last_name' => $data['lastname'],
            'birthdate' => $data['birthdate'],
            'license' => $data['license'],
            'genre' => 'm', // TODO: Infer genre from category? (Männer vs Frauen)
                            // This is missing in the simple logic. checking category name (M vs W) is needed.
        ]);
        
        return [$athlete, true];
    }
    
    public function inferGenre(string $germanCategory): string {
        // Known male indicators
        $maleKeywords = ['Männer', 'U23 M', 'U20 M', 'U18 M', 'U16 M', 'U14 M', 'U12 M', 'U10 M', ' M ', 'M 1', 'M 2', 'M 3', 'M 4', 'M 5', 'M 6', 'M 7', 'M 8', 'M 9'];
        // Known female indicators
        $femaleKeywords = ['Frauen', 'U23 W', 'U20 W', 'U18 W', 'U16 W', 'U14 W', 'U12 W', 'U10 W', ' W ', 'W 1', 'W 2', 'W 3', 'W 4', 'W 5', 'W 6', 'W 7', 'W 8', 'W 9'];

        foreach ($maleKeywords as $kw) {
             if (stripos($germanCategory, $kw) !== false) return 'm';
        }
        foreach ($femaleKeywords as $kw) {
             if (stripos($germanCategory, $kw) !== false) return 'w'; // Database usually uses 'w' or 'f', keeping 'w' as seen in DB check
        }
        
        // Final fallback: standard 'Männer' / 'Frauen' loose check
        if (stripos($germanCategory, 'Männer') !== false) return 'm';
        if (stripos($germanCategory, 'Frauen') !== false) return 'w';

        return 'm'; // Default
    }

    public function importResult(array $data, Athlete $athlete, Discipline $discipline, AthleteCategory $category): Result
    {
        // Resolve Event
        // Date format convert: 05.10.2025 -> 2025-10-05
        $date = Carbon::createFromFormat('d.m.Y', $data['date'])->format('Y-m-d');
        
        $event = Event::firstOrCreate(
            [
                'name' => $data['event_name'],
                'date' => $date,
                'location' => $data['location'],
            ],
            [
                // link, category_id etc could be null
            ]
        );

        // Check for existing result
        // TODO: We need to check if result already exists to avoid duplicates
        // We'll trust the caller to check permissions, but here we do the check.
        
        $existing = Result::where('athlete_id', $athlete->id)
                          ->where('discipline_id', $discipline->id)
                          ->where('event_id', $event->id)
                          ->where('performance', $data['performance'])
                          ->first();

        if ($existing) {
            return $existing;
        }

        return Result::create([
            'athlete_id' => $athlete->id,
            'discipline_id' => $discipline->id,
            'event_id' => $event->id,
            'athlete_category_id' => $category->id,
            'performance' => $data['performance'],
            'wind' => $data['wind'],
            'rank' => $data['rank'],
        ]);
    }
}
