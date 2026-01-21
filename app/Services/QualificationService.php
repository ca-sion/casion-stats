<?php

namespace App\Services;

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
     * @param  array  $limitsJson  The decoded JSON limits file.
     * @param  array  $files  Paths to HTML files or UploadedFile objects.
     * @param  array  $urls  URLs to fetch.
     * @param  string  $clubFilter  Club name to filter by (default 'CA Sion').
     * @param  array  $htmlStrings  Raw HTML strings to parse.
     */
    public function check(array $limitsJson, array $files = [], array $urls = [], string $clubFilter = 'CA Sion', array $htmlStrings = []): array
    {
        $limitDisciplines = collect($limitsJson['disciplines']);
        $triggerDisciplines = $limitDisciplines
            ->filter(fn ($d) => ! empty($d['qualifies_for']))
            ->pluck('discipline')
            ->toArray();
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

            if ($limitConfigs->isEmpty()) {
                continue;
            }

            $analyzedCount++;

            foreach ($limitConfigs as $limitConfig) {
                // B. Determine Category & Limit
                $targetCategory = $this->determineCategory($result, $limitConfig['categories'] ?? []);

                $limitValue = null;
                if ($targetCategory) {
                    $limitValue = $limitConfig['categories'][$targetCategory] ?? null;
                }

                // Gender specific global limits (global_M, global_W)
                if (! $limitValue) {
                    $genderKey = 'global_'.$result['gender']; // global_M or global_W
                    $limitValue = $limitConfig[$genderKey] ?? null;
                    if ($limitValue) {
                        $targetCategory = 'Global '.$result['gender'];
                    }
                }

                // Fallback to absolute global limit
                if (! $limitValue) {
                    $limitValue = $limitConfig['global_limit'] ?? null;
                    if ($limitValue) {
                        $targetCategory = 'Global';
                    }
                }

                if (! $limitValue) {
                    continue;
                }

                // C. Compare Performance
                $limitSeconds = $this->parsePerformanceToSeconds($limitValue);
                $perfSeconds = $result['performance_seconds'];

                if ($limitSeconds === null || $perfSeconds === null) {
                    continue;
                }

                $isField = $this->isFieldEvent($limitConfig['discipline']);

                // Qualify?
                $qualifiedBool = $isField ? ($perfSeconds >= $limitSeconds) : ($perfSeconds <= $limitSeconds);

                if ($qualifiedBool) {
                    $baseResult = array_merge($result, [
                        'limit_hit' => $limitValue,
                        'category_hit' => $targetCategory,
                        'discipline_matched' => $limitConfig['discipline'],
                        'discipline_name' => $limitConfig['discipline'],
                        'status' => 'qualified',
                        'diff_percent' => 0,
                        'limit_hit_discipline' => $limitConfig['discipline'],
                        'has_qualifies_for' => in_array($limitConfig['discipline'], $triggerDisciplines),
                    ]);

                    $qualified->push($baseResult);

                    // Secondary limits: If this discipline qualifies for others
                    if (isset($limitConfig['qualifies_for']) && is_array($limitConfig['qualifies_for'])) {
                        foreach ($limitConfig['qualifies_for'] as $primaryCode) {
                            $secondaryResult = $baseResult;
                            $secondaryResult['discipline_matched'] = $primaryCode;
                            $secondaryResult['discipline_name'] = $primaryCode;
                            $secondaryResult['via_secondary'] = $limitConfig['discipline'];
                            $secondaryResult['has_qualifies_for'] = in_array($primaryCode, $triggerDisciplines);
                            // Keep 'limit_hit' as is (the source limit), but it's now clear it's via secondary
                            $qualified->push($secondaryResult);
                        }
                    }
                    break;
                }

                // Near Miss? (+/- 5%)
                $margin = 0.05;
                $isNearMiss = false;
                $diffPercent = 0;

                if ($limitSeconds > 0) {
                    if ($isField) {
                        // Field: % of limit reached (e.g. 5.80 / 6.00 = 96.6%)
                        $diffPercent = ($perfSeconds / $limitSeconds) * 100;
                        if ($perfSeconds >= $limitSeconds * (1 - $margin)) {
                            $isNearMiss = true;
                        }
                    } else {
                        // Track: % of limit (e.g. 10.30 / 10.00 = 103%)
                        $diffPercent = ($perfSeconds / $limitSeconds) * 100;
                        if ($perfSeconds <= $limitSeconds * (1 + $margin)) {
                            $isNearMiss = true;
                        }
                    }
                }

                if ($isNearMiss) {
                    $qualified->push(array_merge($result, [
                        'limit_hit' => $limitValue,
                        'category_hit' => $targetCategory,
                        'discipline_matched' => $limitConfig['discipline'],
                        'discipline_name' => $limitConfig['discipline'],
                        'status' => 'near_miss',
                        'diff_percent' => round($diffPercent, 1),
                        'limit_hit_discipline' => $limitConfig['discipline'],
                        'has_qualifies_for' => in_array($limitConfig['discipline'], $triggerDisciplines),
                    ]));
                    // Don't break, keep looking for full qualification
                }
            }
        }

        // 3. Deduplicate (Keep best performance per athlete/target_discipline/source_discipline)
        // Grouping by athlete + target discipline + source discipline (direct or secondary source)
        $uniqueQualified = $qualified->groupBy(function ($item) {
            $sourceKey = $item['via_secondary'] ?? 'direct';

            return Str::slug($item['athlete_name']).'|'.$item['discipline_matched'].'|'.$sourceKey;
        })->map(function ($group) {
            $disc = $group->first()['discipline_matched'];
            $isField = $this->isFieldEvent($disc);

            // Prioritize status 'qualified' over 'near_miss' for this specific path
            $hasQualified = $group->contains('status', 'qualified');
            $filtered = $hasQualified ? $group->where('status', 'qualified') : $group;

            if ($isField) {
                return $filtered->sortByDesc('performance_seconds')->first();
            }

            return $filtered->sortBy('performance_seconds')->first();
        });

        $finalData = $uniqueQualified->values()->map(function ($res) use ($rawResults, $limitDisciplines) {
            if (isset($res['via_secondary'])) {
                // Secondary qualification: find the best ACTUAL performance in the PRIMARY discipline
                $primaryPerf = $rawResults->where('athlete_name', $res['athlete_name'])
                    ->where('discipline_name', $res['discipline_matched'])
                    ->sortBy(function ($p) {
                        return $this->isFieldEvent($p['discipline_name']) ? -$p['performance_seconds'] : $p['performance_seconds'];
                    })->first();

                if ($primaryPerf) {
                    $res['primary_performance_display'] = $primaryPerf['performance_display'];
                }

                // Also find the PRIMARY discipline's LIMIT for this athlete/category
                $primaryLimitDict = $limitDisciplines->firstWhere('discipline', $res['discipline_matched']);
                if ($primaryLimitDict) {
                    $limitValue = null;
                    // Re-calculate target category for primary if needed, but usually same
                    $targetCat = $this->determineCategory($res, $primaryLimitDict['categories'] ?? []);
                    if ($targetCat) {
                        $limitValue = $primaryLimitDict['categories'][$targetCat] ?? null;
                    }
                    if (! $limitValue) {
                        $genderKey = 'global_'.$res['gender'];
                        $limitValue = $primaryLimitDict[$genderKey] ?? ($primaryLimitDict['global_limit'] ?? null);
                    }
                    $res['primary_limit'] = $limitValue;
                }

                // Keep secondary info for display
                $res['secondary_perf'] = $res['performance_display'];
                $res['secondary_limit'] = $res['limit_hit'];
            }

            return $res;
        });

        return [
            'data' => $finalData->all(),
            'stats' => [
                'raw_fetched' => $rawResults->count(),
                'analyzed' => $analyzedCount,
                'qualified' => $finalData->where('status', 'qualified')->count(),
                'near_miss' => $finalData->where('status', 'near_miss')->count(),
            ],
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
                    'athlete_name' => $r->athlete->last_name.' '.$r->athlete->first_name,
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
            Log::error("Fetch error $url: ".$e->getMessage());

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
                $discipline = html_entity_decode(trim(strip_tags($m[1])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            } elseif (preg_match('/class="leftheader"[^>]*>\s*(.*?)<\/div>/s', $block, $m)) {
                $discipline = html_entity_decode(trim(strip_tags($m[1])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }

            // 2. Extract Entries
            $entries = explode('class="entryline"', $block);
            array_shift($entries);

            foreach ($entries as $entry) {
                // Filter by Club
                if ($clubFilter && ! str_contains($entry, $clubFilter)) {
                    continue;
                }

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

                if ($perfSeconds === null) {
                    continue;
                }

                $results->push([
                    'source' => $sourceLabel,
                    'athlete_name' => trim($name),
                    'birth_year' => (int) trim($year),
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
        if (isset($categoriesMap[$rawCat])) {
            return $rawCat;
        }

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
            if (isset($categoriesMap[$catName])) {
                return $catName;
            }
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
            return html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return '';
    }

    private function guessGender($catRaw)
    {
        if (str_contains($catRaw, 'W') || str_contains($catRaw, 'F') || str_contains($catRaw, 'Femmes')) {
            return 'W';
        }

        return 'M';
    }

    private function getCategoryNameFromAge($age, $gender)
    {
        $suffix = ($gender === 'M') ? 'M' : 'W'; // Match JSON "M"/"W" or "MAN"/"WOM"?
        // JSON uses "U16M", "MAN", "WOM".

        if ($age < 10) {
            return "U10$suffix";
        }
        if ($age < 12) {
            return "U12$suffix";
        }
        if ($age < 14) {
            return "U14$suffix";
        }
        if ($age < 16) {
            return "U16$suffix";
        }
        if ($age < 18) {
            return "U18$suffix";
        }
        if ($age < 20) {
            return "U20$suffix";
        }
        if ($age < 23) {
            return "U23$suffix";
        }

        return ($gender === 'M') ? 'MAN' : 'WOM';
    }

    private function isFieldEvent($discName)
    {
        $fields = ['hauteur', 'perche', 'longueur', 'triple', 'poids', 'disque', 'marteau', 'javelot', 'balle', 'jump', 'throw', 'shot', 'disk', 'hammer', 'speer', 'kugel', 'hoch', 'stab', 'weit'];
        foreach ($fields as $f) {
            if (str_contains(strtolower($discName), $f)) {
                return true;
            }
        }

        return false;
    }
}
