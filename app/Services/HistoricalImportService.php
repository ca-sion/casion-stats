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
    use \App\Support\PerformanceNormalizer;

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

    public function findDisciplineModel(string $germanName): ?Discipline
    {
        // 1. Try exact match on name_de
        $discipline = Discipline::where('name_de', $germanName)->first();
        if ($discipline) return $discipline;
        
        // 2. Try exact match on name_fr (Legacy/Fallback)
        return Discipline::where('name_fr', $germanName)->first();
    }

    public function findOrMapDiscipline(string $germanName): ?Discipline
    {
        if ($discipline = $this->findDisciplineModel($germanName)) {
            return $discipline;
        }

        // 3. Last resort: Create new discipline with the raw name
        return Discipline::create([
            'name_de' => $germanName,
            'name_fr' => $germanName, // Set as raw name for now
            'type' => 'individual', // Default
        ]);
    }

    public function findCategoryModel(string $germanName): ?AthleteCategory
    {
        // 1. Check exact name_de
        $category = AthleteCategory::where('name_de', $germanName)->first();
        if ($category) return $category;
        
        // 2. Check exact name (Legacy/Fallback)
        return AthleteCategory::where('name', $germanName)->first();
    }

    public function findOrMapCategory(string $germanName): ?AthleteCategory
    {
        if ($category = $this->findCategoryModel($germanName)) {
            return $category;
        }

        // 3. Last resort: Create new category
        return AthleteCategory::create([
            'name' => $germanName,
            'name_de' => $germanName,
        ]);
    }

    public function resolveAthlete(array $data, bool $dryRun = false): array // Returns [Athlete, bool isNew]
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
        
        $candidates = $query->get();
        $nullBirthdateCandidate = null;
        $nullBirthdateCount = 0;

        foreach ($candidates as $candidate) {
            if (!$candidate->birthdate) {
                $nullBirthdateCandidate = $candidate;
                $nullBirthdateCount++;
                continue;
            }

            // Compare years if date exists
            $candidateDate = Carbon::parse($candidate->birthdate);
            $candidateYear = $candidateDate->year;
            $candidateMonth = $candidateDate->month;
            $candidateDay = $candidateDate->day;

            $importDate = Carbon::parse($data['birthdate']);
            $importYear = $importDate->year;
            $importMonth = $importDate->month;
            $importDay = $importDate->day;
            
            if ($candidateYear === $importYear) {
                // Match found! 
                
                // DATA ENRICHMENT: Update birthdate if import has more specific data
                // If candidate only has YYYY-01-01 and import has real month/day
                if ($candidateMonth === 1 && $candidateDay === 1 && ($importMonth !== 1 || $importDay !== 1)) {
                    $candidate->birthdate = $data['birthdate'];
                }

                // Update license if missing
                if (!$candidate->license && !empty($data['license'])) {
                    $candidate->license = $data['license'];
                }

                if ($candidate->isDirty() && !$dryRun) {
                    $candidate->save();
                }

                return [$candidate, false];
            }
        }

        // 3. Fallback: If no year match, but we have exactly ONE candidate with NULL birthdate
        if ($nullBirthdateCount === 1) {
            if (!$dryRun) {
                $nullBirthdateCandidate->birthdate = $data['birthdate'];
                if (!$nullBirthdateCandidate->license && !empty($data['license'])) {
                    $nullBirthdateCandidate->license = $data['license'];
                }
                $nullBirthdateCandidate->save();
            }
            return [$nullBirthdateCandidate, false];
        }

        // 3. Create New
        if ($dryRun) {
            // Return unsaved instance for simple logic
            // Note: Relations won't work on unsaved instances if we rely on IDs later, 
            // but for UI display it's usually fine.
            $athlete = new Athlete([
                'first_name' => $data['firstname'],
                'last_name' => $data['lastname'],
                'birthdate' => $data['birthdate'],
                'license' => $data['license'],
                'genre' => $this->inferGenre($data['raw_category'] ?? ''),
            ]);
            return [$athlete, true];
        }

        $athlete = Athlete::create([
            'first_name' => $data['firstname'],
            'last_name' => $data['lastname'],
            'birthdate' => $data['birthdate'],
            'license' => $data['license'],
            'genre' => $this->inferGenre($data['raw_category'] ?? ''),
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

    /**
     * Resolve event by name, date and location.
     * Uses fuzzy matching for the name if no exact match is found.
     */
    private function resolveEvent(string $name, string $date, string $location): Event
    {
        // 1. Try exact match first
        $event = Event::where('name', $name)
                      ->whereDate('date', $date)
                      ->where('location', $location)
                      ->first();

        if ($event) {
            return $event;
        }

        // 2. Try fuzzy match on name for the same date and location
        $candidates = Event::whereDate('date', $date)
                           ->where('location', $location)
                           ->get();

        $bestMatch = null;
        $highestSimilarity = 0;

        foreach ($candidates as $candidate) {
            // Percent matching using similar_text
            similar_text(mb_strtolower($name), mb_strtolower($candidate->name), $percent);
            
            if ($percent >= 70 && $percent > $highestSimilarity) {
                $highestSimilarity = $percent;
                $bestMatch = $candidate;
            }
        }

        if ($bestMatch) {
            Log::info("Fuzzy matched event: '{$name}' matched to '{$bestMatch->name}' ({$highestSimilarity}%)");
            return $bestMatch;
        }

        // 3. Create new if no match found
        return Event::create([
            'name' => $name,
            'date' => $date,
            'location' => $location,
        ]);
    }

    public function importResult(array $data, Athlete $athlete, Discipline $discipline, AthleteCategory $category): Result
    {
        // Resolve Event
        // Date format convert: 05.10.2025 -> 2025-10-05
        $date = Carbon::createFromFormat('d.m.Y', $data['date'])->format('Y-m-d');
        
        $event = $this->resolveEvent($data['event_name'], $date, $data['location']);

        // Check for existing result to avoid duplicates
        
        $existing = Result::where('athlete_id', $athlete->id)
                          ->where('discipline_id', $discipline->id)
                          ->where('event_id', $event->id)
                          ->where('athlete_category_id', $category->id)
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
            // 'rank' => $data['rank'], // User requested to ignore rank as it's likely "Best List" rank
        ]);
    }
    public function checkResultExists(array $data, Athlete $athlete, Discipline $discipline, AthleteCategory $category): bool
    {
        // 1. Precise check (Same Everything)
        $date = Carbon::createFromFormat('d.m.Y', $data['date'])->format('Y-m-d');
        
        // Normalize performance for comparison (handles "5.4" vs "5.40")
        $normalizedPerf = $this->parsePerformanceToSeconds($data['performance']);

        // 2. Loose check: Check for ANY result for this athlete + discipline + date + normalized performance
        $query = Result::where('athlete_id', $athlete->id)
                          ->where('discipline_id', $discipline->id)
                          ->whereHas('event', function($query) use ($date) {
                              $query->whereDate('date', $date);
                          });

        if ($normalizedPerf !== null) {
            // Robust check using float comparison OR specific string match (for legacy data)
            $query->where(function($q) use ($normalizedPerf, $data) {
                $q->where('performance_normalized', $normalizedPerf)
                  ->orWhere('performance', $data['performance']);
            });
        } else {
            // Fallback to strict string check if normalization fails
            $query->where('performance', $data['performance']);
        }

        return $query->exists();
    }
}
