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

    private array $athleteCache = [];

    /**
     * Clear in-memory caches.
     */
    public function clearCache(): void
    {
        $this->athleteCache = [];
    }

    /**
     * Parse the CSV file and return structured data.
     */
    public function parseCsv(string $filePath): array
    {
        if (! file_exists($filePath) || is_dir($filePath)) {
            return [];
        }

        $content = file_get_contents($filePath);

        return $this->parseCsvString($content);
    }

    /**
     * Parse CSV content from string.
     */
    public function parseCsvString(?string $content): array
    {
        if ($content === null || empty(trim($content))) {
            return [];
        }

        // Strip UTF-8 BOM if present
        $content = preg_replace('/^\x{EF}\x{BB}\x{BF}/u', '', $content);

        $lines = preg_split('/\r\n|\r|\n/', $content);
        $rows = array_map('str_getcsv', $lines);
        $header = array_shift($rows); // Remove header row

        $currentDiscipline = null;
        $currentCategory = null;
        $parsedData = [];

        foreach ($rows as $row) {
            if (empty($row) || ! isset($row[0])) {
                continue;
            }

            $line = trim($row[0]);

            // Check for section header line (e.g., #50m #Männer)
            if (str_starts_with($line, '#')) {
                preg_match_all('/#([^#]+)/', $line, $matches);
                if (! empty($matches[1])) {
                    $currentDiscipline = trim($matches[1][0] ?? '');
                    $currentCategory = trim($matches[1][1] ?? '');
                    $currentInfo = trim($matches[1][2] ?? ''); // e.g., 5000g or 914mm

                    // Combine discipline and info if available for better mapping
                    if ($currentInfo) {
                        $currentDiscipline .= ' '.$currentInfo;
                    }
                }

                continue;
            }

            // If it's a data row and we have context
            if ($currentDiscipline && $currentCategory && count($row) > 10) {
                $row = array_map('trim', $row);

                $parsedData[] = [
                    'raw_discipline' => $currentDiscipline,
                    'raw_category' => $currentCategory,
                    'firstname' => $row[1] ?? '',
                    'lastname' => $row[2] ?? '',
                    'birthdate' => $this->parseDateOfBirth($row[4] ?? '', $row[7] ?? null),
                    'license' => empty($row[5]) ? null : $row[5],
                    'yob' => empty($row[7]) ? null : $row[7],
                    'performance' => $row[9] ?? '',
                    'wind' => empty($row[10]) ? null : $row[10],
                    'rank' => empty($row[11]) ? null : $row[11],
                    'date' => $row[12] ?? '',
                    'location' => $row[13] ?? '',
                    'event_name' => $row[14] ?? '',
                    'country' => $row[15] ?? 'SUI',
                ];
            }
        }

        return $parsedData;
    }

    public function parseDate(string $dateString): string
    {
        $dateString = trim($dateString);
        if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $dateString)) {
            return Carbon::createFromFormat('d.m.Y', $dateString)->format('Y-m-d');
        }
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dateString)) {
            return Carbon::createFromFormat('d/m/Y', $dateString)->format('Y-m-d');
        }

        return Carbon::parse($dateString)->format('Y-m-d');
    }

    private function parseDateOfBirth(?string $dobString, $yob): ?string
    {
        $dobString = trim((string) $dobString);

        $parts = explode('-', $dobString);
        if (count($parts) === 3) {
            $year = (int) $parts[0];
            $month = (int) $parts[1];
            $day = (int) $parts[2];

            if ($year === 0 && $yob) {
                $year = (int) $yob;
            }
            if ($month === 0) {
                $month = 1;
            }
            if ($day === 0) {
                $day = 1;
            }

            if ($year > 0) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        if ($yob && is_numeric($yob) && (int) $yob > 0) {
            return sprintf('%04d-01-01', (int) $yob);
        }

        return null;
    }

    public function findDisciplineModel(string $germanName): ?Discipline
    {
        $germanName = trim($germanName);

        // 1. Try exact match on name_de
        $discipline = Discipline::where('name_de', $germanName)->first();
        if ($discipline) {
            return $discipline;
        }

        // 2. Try exact match on name_fr (Legacy/Fallback)
        $discipline = Discipline::where('name_fr', $germanName)->first();
        if ($discipline) {
            return $discipline;
        }

        // 3. Case-insensitive fallback
        return Discipline::whereRaw('LOWER(TRIM(name_de)) = ?', [mb_strtolower($germanName)])
            ->orWhereRaw('LOWER(TRIM(name_fr)) = ?', [mb_strtolower($germanName)])
            ->first();
    }

    public function findOrMapDiscipline(string $germanName): ?Discipline
    {
        if ($discipline = $this->findDisciplineModel($germanName)) {
            return $discipline;
        }

        // Last resort: Create new discipline with the raw name
        return Discipline::create([
            'name_de' => trim($germanName),
            'name_fr' => trim($germanName),
            'type' => 'individual',
        ]);
    }

    public function getPrimaryEquivalent(AthleteCategory $category): AthleteCategory
    {
        if ($category->is_primary) {
            return $category;
        }

        if ($category->age_limit) {
            $primary = AthleteCategory::where('genre', $category->genre)
                ->where('age_limit', $category->age_limit)
                ->where('is_primary', true)
                ->first();

            return $primary ?? $category;
        }

        return $category;
    }

    public function findCategoryModel(string $germanName): ?AthleteCategory
    {
        $germanName = trim($germanName);

        // 1. Check exact name_de
        $category = AthleteCategory::where('name_de', $germanName)->orderByDesc('is_primary')->first();

        if (! $category) {
            // 2. Check exact name (Legacy/Fallback)
            $category = AthleteCategory::where('name', $germanName)->orderByDesc('is_primary')->first();
        }

        if (! $category) {
            // 3. Case-insensitive fallback
            $category = AthleteCategory::whereRaw('LOWER(TRIM(name_de)) = ?', [mb_strtolower($germanName)])
                ->orWhereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($germanName)])
                ->orderByDesc('is_primary')
                ->first();
        }

        if ($category) {
            return $this->getPrimaryEquivalent($category);
        }

        return null;
    }

    public function findOrMapCategory(string $germanName): ?AthleteCategory
    {
        if ($category = $this->findCategoryModel($germanName)) {
            return $category;
        }

        // Last resort: Create new category
        return AthleteCategory::create([
            'name' => trim($germanName),
            'name_de' => trim($germanName),
        ]);
    }

    public function resolveAthlete(array $data, bool $dryRun = false): array
    {
        $firstname = trim($data['firstname'] ?? '');
        $lastname = trim($data['lastname'] ?? '');
        $license = ! empty($data['license']) ? trim($data['license']) : null;
        $birthdate = ! empty($data['birthdate']) ? trim($data['birthdate']) : null;
        $yob = ! empty($data['yob']) ? (int) $data['yob'] : null;

        // 1. Try by License
        if ($license) {
            $athlete = Athlete::where('license', $license)->first();

            if (! $athlete && isset($this->athleteCache['license:'.$license])) {
                $athlete = $this->athleteCache['license:'.$license];
            }

            if ($athlete) {
                // Enrich data if missing
                if (! $athlete->birthdate && $birthdate) {
                    $athlete->birthdate = $birthdate;
                }

                if ($athlete->isDirty() && ! $dryRun && $athlete->exists) {
                    $athlete->save();
                } elseif (! $dryRun && ! $athlete->exists) {
                    $athlete->save();
                }

                $this->cacheAthlete($athlete);

                return [$athlete, false];
            }
        }

        // 2. Try by Name + DOB (Fuzzy matching)
        $candidates = Athlete::whereRaw('LOWER(TRIM(first_name)) = ?', [mb_strtolower($firstname)])
            ->whereRaw('LOWER(TRIM(last_name)) = ?', [mb_strtolower($lastname)])
            ->get();

        $nullBirthdateCandidate = null;
        $nullBirthdateCount = 0;

        $importYear = null;
        $importMonth = null;
        $importDay = null;

        if ($birthdate) {
            $parsedImportDate = Carbon::parse($birthdate);
            $importYear = $parsedImportDate->year;
            $importMonth = $parsedImportDate->month;
            $importDay = $parsedImportDate->day;
        } elseif ($yob) {
            $importYear = $yob;
        }

        foreach ($candidates as $candidate) {
            if (! $candidate->birthdate) {
                $nullBirthdateCandidate = $candidate;
                $nullBirthdateCount++;

                continue;
            }

            if ($importYear !== null) {
                $candidateDate = Carbon::parse($candidate->birthdate);
                $candidateYear = $candidateDate->year;
                $candidateMonth = $candidateDate->month;
                $candidateDay = $candidateDate->day;

                if ($candidateYear === $importYear) {
                    // Match found!
                    // DATA ENRICHMENT: Update birthdate if candidate only has YYYY-01-01 and import has real month/day
                    if ($candidateMonth === 1 && $candidateDay === 1 && $importMonth !== null && ($importMonth !== 1 || $importDay !== 1)) {
                        $candidate->birthdate = $birthdate;
                    }

                    // Update license if missing
                    if (! $candidate->license && $license) {
                        $candidate->license = $license;
                    }

                    if ($candidate->isDirty() && ! $dryRun) {
                        $candidate->save();
                    }

                    $this->cacheAthlete($candidate);

                    return [$candidate, false];
                }
            }
        }

        // Fallback: If no year match, but exactly ONE candidate with NULL birthdate
        if ($nullBirthdateCount === 1 && $nullBirthdateCandidate) {
            if ($birthdate) {
                $nullBirthdateCandidate->birthdate = $birthdate;
            }
            if (! $nullBirthdateCandidate->license && $license) {
                $nullBirthdateCandidate->license = $license;
            }

            if (! $dryRun) {
                $nullBirthdateCandidate->save();
            }

            $this->cacheAthlete($nullBirthdateCandidate);

            return [$nullBirthdateCandidate, false];
        }

        // Check local cache for unsaved or newly created athlete in this batch
        $cacheKey = mb_strtolower($firstname.'_'.$lastname.'_'.($importYear ?? 'any'));
        if (isset($this->athleteCache[$cacheKey])) {
            $cachedAthlete = $this->athleteCache[$cacheKey];
            if (! $dryRun && ! $cachedAthlete->exists) {
                $cachedAthlete->save();
            }

            return [$cachedAthlete, true];
        }

        // 3. Create New Athlete
        $genre = $this->inferGenre($data['raw_category'] ?? '');

        if ($dryRun) {
            $athlete = new Athlete([
                'first_name' => $firstname,
                'last_name' => $lastname,
                'birthdate' => $birthdate,
                'license' => $license,
                'genre' => $genre,
            ]);

            $this->cacheAthlete($athlete);

            return [$athlete, true];
        }

        $athlete = Athlete::create([
            'first_name' => $firstname,
            'last_name' => $lastname,
            'birthdate' => $birthdate,
            'license' => $license,
            'genre' => $genre,
        ]);

        $this->cacheAthlete($athlete);

        return [$athlete, true];
    }

    private function cacheAthlete(Athlete $athlete): void
    {
        $fn = trim($athlete->first_name ?? '');
        $ln = trim($athlete->last_name ?? '');
        $year = $athlete->birthdate ? Carbon::parse($athlete->birthdate)->year : 'any';

        $key = mb_strtolower($fn.'_'.$ln.'_'.$year);
        $this->athleteCache[$key] = $athlete;

        if (! empty($athlete->license)) {
            $this->athleteCache['license:'.$athlete->license] = $athlete;
        }
    }

    public function inferGenre(string $germanCategory): string
    {
        $maleKeywords = ['Männer', 'U23 M', 'U20 M', 'U18 M', 'U16 M', 'U14 M', 'U12 M', 'U10 M', ' M ', 'M 1', 'M 2', 'M 3', 'M 4', 'M 5', 'M 6', 'M 7', 'M 8', 'M 9', 'Männlich', 'Hommes'];
        $femaleKeywords = ['Frauen', 'U23 W', 'U20 W', 'U18 W', 'U16 W', 'U14 W', 'U12 W', 'U10 W', ' W ', 'W 1', 'W 2', 'W 3', 'W 4', 'W 5', 'W 6', 'W 7', 'W 8', 'W 9', 'Weiblich', 'Femmes'];

        foreach ($maleKeywords as $kw) {
            if (stripos($germanCategory, $kw) !== false) {
                return 'm';
            }
        }
        foreach ($femaleKeywords as $kw) {
            if (stripos($germanCategory, $kw) !== false) {
                return 'w';
            }
        }

        if (preg_match('/[0-9]\s*M$/i', trim($germanCategory)) || preg_match('/^M$/i', trim($germanCategory))) {
            return 'm';
        }
        if (preg_match('/[0-9]\s*W$/i', trim($germanCategory)) || preg_match('/^W$/i', trim($germanCategory))) {
            return 'w';
        }

        if (stripos($germanCategory, 'Männer') !== false) {
            return 'm';
        }
        if (stripos($germanCategory, 'Frauen') !== false) {
            return 'w';
        }

        return 'm';
    }

    /**
     * Resolve event by name, date and location.
     * Uses fuzzy matching for the name if no exact match is found.
     */
    private function resolveEvent(string $name, string $date, string $location): Event
    {
        $name = trim($name);
        $location = trim($location);
        $formattedDate = $this->parseDate($date);

        // 1. Try exact match first
        $event = Event::where('name', $name)
            ->whereDate('date', $formattedDate)
            ->where('location', $location)
            ->first();

        if ($event) {
            return $event;
        }

        // 2. Try fuzzy match on name & location for the same date
        $candidates = Event::whereDate('date', $formattedDate)->get();

        $bestMatch = null;
        $highestSimilarity = 0;

        foreach ($candidates as $candidate) {
            similar_text(mb_strtolower($name), mb_strtolower($candidate->name), $nameSimilarity);

            $candLoc = mb_strtolower(trim($candidate->location ?? ''));
            $inputLoc = mb_strtolower(trim($location));

            $locationMatch = false;
            if ($candLoc === $inputLoc || empty($candLoc) || empty($inputLoc)) {
                $locationMatch = true;
            } else {
                similar_text($candLoc, $inputLoc, $locSimilarity);
                if ($locSimilarity >= 65 || str_contains($candLoc, $inputLoc) || str_contains($inputLoc, $candLoc)) {
                    $locationMatch = true;
                }
            }

            // Only consider candidates where location is compatible
            if ($locationMatch && $nameSimilarity >= 70 && $nameSimilarity > $highestSimilarity) {
                $highestSimilarity = $nameSimilarity;
                $bestMatch = $candidate;
            }
        }

        if ($bestMatch) {
            try {
                Log::info("Fuzzy matched event: '{$name}' matched to '{$bestMatch->name}' ({$highestSimilarity}%)");
            } catch (\Throwable $e) {
                // Ignore log errors
            }

            return $bestMatch;
        }

        // 3. Create new if no match found
        return Event::create([
            'name' => $name,
            'date' => $formattedDate,
            'location' => $location,
        ]);
    }

    /**
     * Find an existing duplicate result for an athlete, discipline, date, and performance.
     */
    public function findExistingResult(array $data, Athlete $athlete, Discipline $discipline, ?AthleteCategory $category = null): ?Result
    {
        if (! $athlete->id || ! $discipline->id || empty($data['date'])) {
            return null;
        }

        $date = $this->parseDate($data['date']);
        $normalizedPerf = $this->parsePerformanceToSeconds($data['performance']);

        $query = Result::where('athlete_id', $athlete->id)
            ->where('discipline_id', $discipline->id)
            ->whereHas('event', function ($q) use ($date) {
                $q->whereDate('date', $date);
            });

        if ($normalizedPerf !== null) {
            $query->where(function ($q) use ($normalizedPerf, $data) {
                $q->where('performance_normalized', $normalizedPerf)
                    ->orWhere('performance', trim($data['performance']));
            });
        } else {
            $query->where('performance', trim($data['performance']));
        }

        return $query->first();
    }

    /**
     * Check if a result already exists in the database.
     */
    public function checkResultExists(array $data, Athlete $athlete, Discipline $discipline, ?AthleteCategory $category = null): bool
    {
        if (! $athlete->exists && ! $athlete->id) {
            return false;
        }

        return $this->findExistingResult($data, $athlete, $discipline, $category) !== null;
    }

    /**
     * Get a specificity score for a category. Higher score = more specific age-group category.
     */
    public function getCategoryPriority(?AthleteCategory $category, ?string $rawCategoryName = ''): int
    {
        $name = $category ? ($category->name . ' ' . ($category->name_de ?? '')) : (string) $rawCategoryName;
        $score = 0;

        // Age group categories (e.g. U14, U16, U18, U20, U23)
        if (preg_match('/U\s*(\d+)/i', $name, $m)) {
            $age = (int) $m[1];
            $score += (100 - $age) + 50; // younger age-group = more specific
        } elseif (preg_match('/[MW]\s*(\d+)/i', $name, $m)) {
            // Masters category (e.g. M35, W40)
            $score += 40;
        } elseif ($category && ($category->is_youth || $category->age_limit !== null)) {
            $score += 50;
        }

        if ($category && $category->is_primary) {
            $score += 10;
        }

        return $score;
    }

    /**
     * Import a single result. If it already exists, enrich it and return the existing record.
     */
    public function importResult(array $data, Athlete $athlete, Discipline $discipline, AthleteCategory $category): Result
    {
        $existing = $this->findExistingResult($data, $athlete, $discipline, $category);

        if ($existing) {
            // Enrich missing wind if present in import
            if (empty($existing->wind) && ! empty($data['wind'])) {
                $existing->wind = trim($data['wind']);
            }

            // Upgrade category if the new one is more specific (e.g. U16 vs WOM)
            $existingCat = $existing->athleteCategory;
            $newPriority = $this->getCategoryPriority($category);
            $existingPriority = $this->getCategoryPriority($existingCat);

            if ($newPriority > $existingPriority) {
                $existing->athlete_category_id = $category->id;
            }

            if ($existing->isDirty()) {
                $existing->save();
            }

            return $existing;
        }

        $formattedDate = $this->parseDate($data['date']);
        $event = $this->resolveEvent($data['event_name'], $formattedDate, $data['location']);

        return Result::create([
            'athlete_id' => $athlete->id,
            'discipline_id' => $discipline->id,
            'event_id' => $event->id,
            'athlete_category_id' => $category->id,
            'performance' => trim($data['performance']),
            'wind' => ! empty($data['wind']) ? trim($data['wind']) : null,
        ]);
    }
}
