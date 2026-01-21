<?php

namespace App\Services;

use App\Models\Athlete;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AthleteDeduplicationService
{
    /**
     * Find potential duplicate athletes.
     * Returns a collection of clusters. Each cluster is an array of Athletes.
     */
    public function findDuplicates(): Collection
    {
        // Optimization: Bucket by Soundex of Last Name to reduce comparison space.
        // O(N^2) on 3000 items is 9M comparisons -> Timeout.
        // Bucketing creates smaller groups.

        $athletes = Athlete::all();
        $processedIds = []; // Global processed list to avoid duplicates across buckets (though unlikely to cross buckets)
        $clusters = [];

        // Bucket 1: Soundex of Lastname (Catches typos: Savioz vs Saviose)
        $buckets = $athletes->groupBy(function ($athlete) {
            return soundex($athlete->last_name);
        });

        foreach ($buckets as $bucket) {
            if ($bucket->count() < 2) {
                continue;
            }

            $group = $bucket->values();
            $count = $group->count();

            // Compare within bucket
            for ($i = 0; $i < $count; $i++) {
                $a = $group[$i];
                if (in_array($a->id, $processedIds)) {
                    continue;
                }

                $cluster = [$a];
                // Mark A as processed for this pass?
                // We mark it later if it becomes part of a cluster,
                // OR we mark it now ensuring we don't start a new cluster with it?
                // Actually, if we start a cluster with A, we should not process A again.
                // But we must let A be compared against B, C...
                // If B forms a cluster with A, B is also processed.

                $hasDuplicates = false;

                for ($j = $i + 1; $j < $count; $j++) {
                    $b = $group[$j];
                    if (in_array($b->id, $processedIds)) {
                        continue;
                    }

                    if ($this->arePotentialDuplicates($a, $b)) {
                        $cluster[] = $b;
                        $processedIds[] = $b->id; // B is part of A's cluster
                        $hasDuplicates = true;
                    }
                }

                if ($hasDuplicates) {
                    $clusters[] = $cluster;
                    $processedIds[] = $a->id; // A is done
                }
            }
        }

        // Pass 2: Flipped Names (Special case check, usually fast)
        // Check for "John Doe" vs "Doe John"
        // Iterate all, if not processed, check if there is an athlete with swapped names
        // This is tricky efficiently.
        // We can create a Lookup Map: "tolower(firstname_lastname)" => ID
        // If "tolower(lastname_firstname)" exists in map, match.

        $nameMap = [];
        foreach ($athletes as $athlete) {
            $key = strtolower($athlete->first_name.'_'.$athlete->last_name);
            $nameMap[$key][] = $athlete;
        }

        foreach ($athletes as $athlete) {
            if (in_array($athlete->id, $processedIds)) {
                continue;
            }

            $swappedKey = strtolower($athlete->last_name.'_'.$athlete->first_name);
            if (isset($nameMap[$swappedKey])) {
                foreach ($nameMap[$swappedKey] as $match) {
                    if ($match->id !== $athlete->id && ! in_array($match->id, $processedIds)) {
                        $clusters[] = [$athlete, $match];
                        $processedIds[] = $athlete->id;
                        $processedIds[] = $match->id;
                        break; // Handle pair
                    }
                }
            }
        }

        return collect($clusters);
    }

    public function arePotentialDuplicates(Athlete $a, Athlete $b): bool
    {
        // 1. Exact Name Match
        if (strtolower($a->first_name) === strtolower($b->first_name) &&
            strtolower($a->last_name) === strtolower($b->last_name)) {
            return true;
        }

        // 2. Fuzzy Name Match
        // Use PHP's similar_text or levenshtein
        // If birthdate matches (full or year), allow looser name match

        $nameA = strtolower($a->first_name.' '.$a->last_name);
        $nameB = strtolower($b->first_name.' '.$b->last_name);

        $lev = levenshtein($nameA, $nameB);
        $percent = 0;
        similar_text($nameA, $nameB, $percent);

        $sameBirthYear = false;
        if ($a->birthdate && $b->birthdate) {
            $yearA = substr($a->birthdate, 0, 4);
            $yearB = substr($b->birthdate, 0, 4);
            $sameBirthYear = ($yearA === $yearB);
        }

        // High Similarity (> 90%) - likely typo
        if ($percent > 90) {
            return true;
        }

        // Moderate Similarity (> 80%) WITH Same Birth Year
        if ($percent > 80 && $sameBirthYear) {
            return true;
        }

        // Check for flipped names (First Last vs Last First)
        $flippedNameB = strtolower($b->last_name.' '.$b->first_name);
        if ($nameA === $flippedNameB) {
            return true;
        }

        return false;
    }

    public function mergeAthletes(Athlete $primary, Athlete $secondary): bool
    {
        return DB::transaction(function () use ($primary, $secondary) {
            // 1. Move Results
            // We need to be careful about duplicate results (same event/discipline)
            // But usually we just move them.

            // Update results where athlete_id = secondary->id to primary->id
            \App\Models\Result::where('athlete_id', $secondary->id)
                ->update(['athlete_id' => $primary->id]);

            // 2. Merge Metadata (fill gaps in primary)
            if (empty($primary->birthdate) && ! empty($secondary->birthdate)) {
                $primary->birthdate = $secondary->birthdate;
            }
            if (empty($primary->license) && ! empty($secondary->license)) {
                $primary->license = $secondary->license;
            }
            if (empty($primary->genre) && ! empty($secondary->genre)) {
                $primary->genre = $secondary->genre;
            }
            // Add any other fields...

            $primary->save();

            // 3. Delete Secondary
            $secondary->delete();

            return true;
        });
    }
}
