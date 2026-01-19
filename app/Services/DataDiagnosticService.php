<?php

namespace App\Services;

use App\Models\Result;
use App\Models\AthleteCategory;
use Illuminate\Support\Facades\Log;

class DataDiagnosticService
{
    /**
     * Get diagnostics for a given result.
     * Returns an array of issues found.
     */
    public function getDiagnostics(Result $result): array
    {
        $issues = [];

        // Ensure relations are loaded
        if (!$result->relationLoaded('athlete')) {
            $result->load('athlete');
        }
        if (!$result->relationLoaded('athleteCategory')) {
            $result->load('athleteCategory');
        }
        if (!$result->relationLoaded('event')) {
            $result->load('event');
        }

        // 0. Check for missing relations
        if (!$result->athlete) {
            $issues[] = [
                'type' => 'missing_relation',
                'label' => "Athlète manquant (ID: {$result->athlete_id})",
                'severity' => 'error',
                'sql_fix' => "DELETE FROM results WHERE id = {$result->id};",
            ];
            return $issues;
        }

        if (!$result->athleteCategory) {
            $issues[] = [
                'type' => 'missing_relation',
                'label' => "Catégorie manquante (ID: {$result->athlete_category_id})",
                'severity' => 'error',
                'sql_fix' => "DELETE FROM results WHERE id = {$result->id};",
            ];
            return $issues;
        }

        if (!$result->event) {
            $issues[] = [
                'type' => 'missing_relation',
                'label' => "Événement manquant (ID: {$result->event_id})",
                'severity' => 'error',
                'sql_fix' => "DELETE FROM results WHERE id = {$result->id};",
            ];
            return $issues;
        }

        // 1. Genre mismatch
        if ($result->athlete->genre !== $result->athleteCategory->genre) {
            $issues[] = [
                'type' => 'genre_mismatch',
                'label' => "Genre {$result->athlete->genre} ≠ Cat {$result->athleteCategory->genre}",
                'severity' => 'warning',
                'sql_fix' => "UPDATE athletes SET genre = '{$result->athleteCategory->genre}' WHERE id = {$result->athlete->id};",
            ];
        }

        // 2. Age category mismatch (Athletic Age: Year-based)
        $athleticAge = $result->event->date->year - $result->athlete->birthdate->year;
        $hasValidBirthdate = $result->athlete->birthdate->year > 1900;

        if (!$hasValidBirthdate) {
            $issues[] = [
                'type' => 'missing_birthdate',
                'label' => "Date de naissance manquante",
                'severity' => 'warning',
                'sql_fix' => "UPDATE athletes SET birthdate = 'YYYY-MM-DD' WHERE id = {$result->athlete->id};",
            ];
        } elseif ($result->athleteCategory->age_limit) {
            // Check if it's an "exact age" category (e.g. U10 W08, U16 M15)
            $isExactAge = preg_match('/[MW]\d{2}$/', $result->athleteCategory->name);

            if ($isExactAge) {
                if ($athleticAge != $result->athleteCategory->age_limit) {
                    $suggestedCategory = AthleteCategory::where('genre', $result->athlete->genre)
                        ->where('name', 'LIKE', '% ' . ($athleticAge < 10 ? '0' : '') . $athleticAge)
                        ->get()
                        ->sortBy(fn($cat) => preg_match('/\d{2}$/', $cat->name))
                        ->first();

                    $issues[] = [
                        'type' => 'age_mismatch',
                        'label' => "Âge ({$athleticAge} ans) ≠ {$result->athleteCategory->age_limit} attendu",
                        'severity' => 'warning',
                        'suggested_category_id' => $suggestedCategory?->id,
                        'sql_fix' => $suggestedCategory 
                            ? "UPDATE results SET athlete_category_id = {$suggestedCategory->id} WHERE id = {$result->id};"
                            : null,
                    ];
                }
            } else {
                // Regular "Under" category
                if ($athleticAge > $result->athleteCategory->age_limit) {
                    $suggestedCategory = AthleteCategory::where('genre', $result->athlete->genre)
                        ->where('age_limit', '>=', $athleticAge)
                        ->orderBy('age_limit', 'asc')
                        ->get()
                        ->sortBy(fn($cat) => preg_match('/\d{2}$/', $cat->name))
                        ->first();
                    
                    if (!$suggestedCategory) {
                        $suggestedCategory = AthleteCategory::where('genre', $result->athlete->genre)
                            ->where('age_limit', 99)
                            ->first();
                    }

                    $issues[] = [
                        'type' => 'age_mismatch',
                        'label' => "Âge ({$athleticAge} ans) > Limite {$result->athleteCategory->age_limit}",
                        'severity' => 'warning',
                        'suggested_category_id' => $suggestedCategory?->id,
                        'sql_fix' => $suggestedCategory 
                            ? "UPDATE results SET athlete_category_id = {$suggestedCategory->id} WHERE id = {$result->id};"
                            : null,
                    ];
                } else {
                    $optimalCategory = AthleteCategory::where('genre', $result->athlete->genre)
                        ->where('age_limit', '>=', $athleticAge)
                        ->where('age_limit', '<=', $result->athleteCategory->age_limit)
                        ->get()
                        ->filter(fn($cat) => !preg_match('/\d{2}$/', $cat->name))
                        ->sortBy('age_limit')
                        ->first();

                    if ($optimalCategory && $optimalCategory->id !== $result->athlete_category_id) {
                        $issues[] = [
                            'type' => 'age_mismatch',
                            'label' => "Cat {$result->athleteCategory->name} alors que {$optimalCategory->name} possible ({$athleticAge} ans)",
                            'severity' => 'info',
                            'suggested_category_id' => $optimalCategory->id,
                            'sql_fix' => "UPDATE results SET athlete_category_id = {$optimalCategory->id} WHERE id = {$result->id};",
                        ];
                    }
                }
            }
        }

        // 3. Potential duplicate
        $duplicate = Result::where('athlete_id', $result->athlete_id)
            ->where('discipline_id', $result->discipline_id)
            ->where('id', '!=', $result->id)
            ->whereHas('event', function ($query) use ($result) {
                $query->where('date', $result->event->date);
            })
            ->exists();

        if ($duplicate) {
            $issues[] = [
                'type' => 'duplicate',
                'label' => "Doublon potentiel (même jour)",
                'severity' => 'info',
                'sql_fix' => "DELETE FROM results WHERE id = {$result->id};",
            ];
        }

        // 4. Performance format check
        if (!preg_match('/^\d+([.:]\d+)*$/', $result->performance)) {
             $issues[] = [
                'type' => 'format_issue',
                'label' => "Format performance suspect: '{$result->performance}'",
                'severity' => 'info',
                'sql_fix' => "UPDATE results SET performance = '{$result->performance}' WHERE id = {$result->id};",
            ];
        }

        return $issues;
    }
}
