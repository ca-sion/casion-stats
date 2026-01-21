<?php

namespace App\Services;

use App\Models\AthleteCategory;
use App\Models\Result;
use App\Support\PerformanceNormalizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QualificationService
{
    use PerformanceNormalizer;

    /**
     * Check qualifications against limits.
     *
     * @param array $limitsJson The decoded JSON limits file.
     * @param array $files Paths to HTML files or UploadedFile objects.
     * @param array $urls URLs to fetch.
     * @param string $clubFilter Club name to filter by (default 'CA Sion').
     * @param array $htmlStrings Raw HTML strings to parse.
     * @return array
     */
    public function check(array $limitsJson, array $files = [], array $urls = [], string $clubFilter = 'CA Sion', array $htmlStrings = []): array
    {
        $limitDisciplines = collect($limitsJson['disciplines']);
        $years = $limitsJson['years'] ?? [now()->year];

        // 1. Collect ALL Raw Results (DB + HTML + URL)
        $rawResults = collect();

        // A. From Database
        $rawResults = $rawResults->merge($this->fetchFromDatabase($years));

        // B. From Files
        foreach ($files as $file) {
            $path = $file instanceof \Illuminate\Http\UploadedFile ? $file->getRealPath() : $file;
            $content = file_get_contents($path);
            $rawResults = $rawResults->merge($this->parseHtml($content, 'File', $clubFilter));
        }

        // C. From URLs
        foreach ($urls as $url) {
            $content = $this->fetchUrlContent($url);
            if ($content) {
                $rawResults = $rawResults->merge($this->parseHtml($content, 'URL', $clubFilter));
            }
        }

         // D. From Raw Strings
        foreach ($htmlStrings as $html) {
            $rawResults = $rawResults->merge($this->parseHtml($html, 'String', $clubFilter));
        }

        // 2. Process & Verify
        $qualified = collect();
        $analyzedCount = 0;

        foreach ($rawResults as $result) {
            // A. Match ALL Disciplines that might fit
            // e.g. "50m haies" might match "50mH (84.0)" AND "50mH (91.4)"
            $limitConfigs = $this->matchDisciplines($result['discipline_raw'], $limitDisciplines);
            
            if ($limitConfigs->isEmpty()) continue;

            $analyzedCount++;

            foreach ($limitConfigs as $limitConfig) {
                // B. Determine Category & Limit
                $targetCategory = $this->determineCategory($result, $limitConfig['categories'] ?? []);
                if (!$targetCategory) continue; 

                $limitValue = $limitConfig['categories'][$targetCategory] ?? null;
                if (!$limitValue) {
                    $limitValue = $limitConfig['global_limit'] ?? null;
                }

                if (!$limitValue) continue; 

                // C. Compare Performance
                $limitSeconds = $this->parsePerformanceToSeconds($limitValue);
                $perfSeconds = $result['performance_seconds'];

                if ($limitSeconds === null || $perfSeconds === null) continue;

                $isField = $this->isFieldEvent($limitConfig['discipline']);
                $qualifiedBool = $isField ? ($perfSeconds >= $limitSeconds) : ($perfSeconds <= $limitSeconds);

                if ($qualifiedBool) {
                    $result['limit_hit'] = $limitValue;
                    $result['category_hit'] = $targetCategory;
                    $result['discipline_matched'] = $limitConfig['discipline'];
                    $result['discipline_name'] = $limitConfig['discipline'];
                    $qualified->push($result);
                    break; // If qualified in one variant, we are good? Or keep best? 
                           // Simplest: Qualified is Qualified.
                }
            }
        }

        // 3. Deduplicate (Keep best performance per athlete/discipline)
        // Group by Athlete + Main Discipline Name (ignore variants like 84.0?)
        // Actually, we want to list exactly what they qualified for.
        
        $uniqueQualified = $qualified->groupBy(function ($item) {
            return Str::slug($item['athlete_name']) . '|' . $item['discipline_matched'];
        })->map(function ($group) {
            $disc = $group->first()['discipline_matched'];
            $isField = $this->isFieldEvent($disc);

            if ($isField) {
                return $group->sortByDesc('performance_seconds')->first();
            }
            return $group->sortBy('performance_seconds')->first();
        });

        return [
            'data' => $uniqueQualified->values()->all(),
            'stats' => [
                'raw_fetched' => $rawResults->count(),
                'analyzed' => $analyzedCount,
                'qualified' => $uniqueQualified->count(),
            ]
        ];
    }

    // --- Data Sources ---

    private function fetchFromDatabase(array $years): Collection
    {
        return Result::with(['athlete', 'discipline', 'event', 'athleteCategory'])
            ->whereHas('event', function ($q) use ($years) {
                $q->where(function ($sub) use ($years) {
                    foreach ($years as $year) {
                        $sub->orWhereYear('date', $year);
                    }
                });
            })
            ->get()
            ->map(function ($r) {
                // DB source
                return [
                    'source' => 'DB',
                    'athlete_name' => $r->athlete->last_name . ' ' . $r->athlete->first_name,
                    'birth_year' => $r->athlete->birthdate->year,
                    'gender' => strtoupper($r->athlete->genre), // 'M' or 'W'
                    'category_db' => $r->athleteCategory->name ?? null,
                    'discipline_raw' => $r->discipline->name_fr ?: $r->discipline->name,
                    'discipline_name' => $r->discipline->name_fr ?: $r->discipline->name,
                    'performance_display' => $r->performance,
                    'performance_formatted' => $r->performance,
                    'performance_seconds' => $r->performance_normalized,
                    'date' => $r->event->date->format('Y-m-d'),
                    'year' => $r->event->date->year,
                ];
            });
    }

    private function fetchUrlContent($url)
    {
        try {
            $resp = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])->get($url);
            return $resp->successful() ? $resp->body() : null;
        } catch (\Exception $e) {
            Log::error("Fetch error $url: " . $e->getMessage());
            return null;
        }
    }

    private function parseHtml($content, $sourceLabel, string $clubFilter): Collection
    {
        if (str_contains($content, '=3D')) {
            $content = quoted_printable_decode($content);
        }

        $results = collect();
        // Split by discipline blocks
        $blocks = explode('class="listheader"', $content);
        array_shift($blocks); // Remove preamble

        foreach ($blocks as $block) {
            // 1. Extract Discipline Name
            $discipline = 'Unknown';
            // Try explicit link text first, then div text
            if (preg_match('/class="leftheader"[^>]*>\s*<a[^>]*>(.*?)<\/a>/s', $block, $m)) {
                $discipline = trim(strip_tags($m[1]));
            } elseif (preg_match('/class="leftheader"[^>]*>\s*(.*?)<\/div>/s', $block, $m)) {
                $discipline = trim(strip_tags($m[1]));
            }

            // 2. Extract Entries
            $entries = explode('class="entryline"', $block);
            array_shift($entries);

            foreach ($entries as $entry) {
                // Filter by Club
                if ($clubFilter && !str_contains($entry, $clubFilter)) continue;

                $name = $this->extractRegex('/class="col-2".*?class="firstline">\s*(.*?)\s*<\/div>/s', $entry);
                $year = $this->extractRegex('/class="col-3".*?class="secondline">\s*(.*?)\s*<\/div>/s', $entry);
                $catRaw = $this->extractRegex('/class="col-4".*?class="firstline">\s*(.*?)\s*<\/div>/s', $entry, true); // Use all matches for category? No, col-4 firstline is usually Perf. Wait.
                
                // Correction on Regex based on debug:
                // Perf is in col-4 firstline.
                // Cat is... where?
                // In Step 481 Output:
                // <div class="col-4"><div class="firstline">U16M</div>...</div> is AFTER the perf col-4?
                // Actually there are MULTIPLE col-4s.
                // 1st col-4: Result (8,45)
                // 2nd col-4: Category (U16M)
                
                // Let's grab all col-4 contents
                preg_match_all('/class="col-4".*?class="firstline">\s*(.*?)\s*<\/div>/s', $entry, $col4Matches);
                $perfRaw = $col4Matches[1][0] ?? 'DNS';
                $catRaw = $col4Matches[1][1] ?? '';

                $perfRaw = str_replace(',', '.', trim($perfRaw));
                $perfSeconds = $this->parsePerformanceToSeconds($perfRaw);

                if ($perfSeconds === null) continue;

                $results->push([
                    'source' => $sourceLabel,
                    'athlete_name' => trim($name),
                    'birth_year' => (int)trim($year),
                    'gender' => $this->guessGender($catRaw), // Heuristic
                    'category_db' => trim($catRaw), // Raw category from HTML
                    'discipline_raw' => trim($discipline),
                    'discipline_name' => trim($discipline), // Temporary, will be overriden if matched
                    'performance_display' => $perfRaw,
                    'performance_formatted' => $perfRaw,
                    'performance_seconds' => $perfSeconds,
                    'date' => now()->format('Y-m-d'), // Assume current
                    'year' => now()->year,
                ]);
            }
        }
        return $results;
    }

    // --- Matching Logic ---

    private function matchDisciplines(string $rawName, Collection $limitDisciplines): Collection
    {
        $rawNorm = $this->normalize($rawName);

        // Strategy: Find ALL JSON Limit Keys that are contained in the Raw Name (or vice versa)
        // because we don't know which variant applies (e.g. 84.0 vs 91.4).
        
        $matches = collect();

        foreach ($limitDisciplines as $limit) {
            $limitName = $limit['discipline'];
            $limitNorm = $this->normalize($limitName);

            // Check containment
            if (str_contains($rawNorm, $limitNorm)) {
                $matches->push($limit);
            }
        }
        
        return $matches;
    }

    private function determineCategory($result, $categoriesMap)
    {
        // 1. Try mapping the Raw Category directly (e.g. "U16M" -> "U16M")
        $rawCat = strtoupper(str_replace(' ', '', $result['category_db'])); // "U16 M" -> "U16M"
        if (isset($categoriesMap[$rawCat])) return $rawCat;

        // 2. If no direct match, calculate from Birth Year
        // (This handles "U16 M15" or cases where HTML cat is weird)
        if ($result['birth_year'] > 0) {
            $age = now()->year - $result['birth_year'];
            $gender = $result['gender']; // M/W

            // Map Age to Standard Categories (U16M, etc)
            // Hardcoded Logic or iterate keys?
            // Let's iterate keys in $categoriesMap to find one that fits.
            // But JSON keys don't have age limits defined in them implicitly (except U16).
            // Better to rely on standard Swiss rules or specific helper.
            
            $catName = $this->getCategoryNameFromAge($age, $gender);
            if (isset($categoriesMap[$catName])) return $catName;
        }

        return null;
    }

    // --- Helpers ---

    private function normalize($str)
    {
        $str = strtolower($str);
        // Remove content in parenthesis (e.g. weights, heights)
        $str = preg_replace('/\(.*?\)/', '', $str);
        
        $str = str_replace(
            ['haies', 'hurdles', 'mètres', 'metres', ' '], 
            ['h', 'h', 'm', 'm', ''], 
            $str
        );
        // Remove dots left over?
        return str_replace(['.', ','], '', $str);
    }

    private function extractRegex($pattern, $subject, $all = false)
    {
        if (preg_match($pattern, $subject, $m)) {
            return trim($m[1]);
        }
        return '';
    }

    private function guessGender($catRaw)
    {
        if (str_contains($catRaw, 'W') || str_contains($catRaw, 'F') || str_contains($catRaw, 'Femmes')) return 'W';
        return 'M';
    }

    private function getCategoryNameFromAge($age, $gender)
    {
        $suffix = ($gender === 'M') ? 'M' : 'W'; // Match JSON "M"/"W" or "MAN"/"WOM"?
        // JSON uses "U16M", "MAN", "WOM".
        
        if ($age < 10) return "U10$suffix";
        if ($age < 12) return "U12$suffix";
        if ($age < 14) return "U14$suffix";
        if ($age < 16) return "U16$suffix";
        if ($age < 18) return "U18$suffix";
        if ($age < 20) return "U20$suffix";
        if ($age < 23) return "U23$suffix";
        
        return ($gender === 'M') ? 'MAN' : 'WOM';
    }

    private function isFieldEvent($discName)
    {
        $fields = ['hauteur', 'perche', 'longueur', 'triple', 'poids', 'disque', 'marteau', 'javelot', 'balle', 'jump', 'throw', 'shot', 'disk', 'hammer', 'speer', 'kugel', 'hoch', 'stab', 'weit'];
        foreach ($fields as $f) {
            if (str_contains(strtolower($discName), $f)) return true;
        }
        return false;
    }
}
