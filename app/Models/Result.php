<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Result extends Model
{
    use HasFactory;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'performance_normalized' => 'float',
    ];

    /**
     * Scope a query to filter by discipline.
     */
    public function scopeForDiscipline($query, $disciplineId)
    {
        return $query->where('discipline_id', $disciplineId);
    }

    /**
     * Scope a query to filter by athlete category.
     */
    public function scopeForCategory($query, $categoryId)
    {
        return $query->when($categoryId, function ($query, $categoryId) {
            $query->where('athlete_category_id', $categoryId);
        });
    }

    /**
     * Scope a query to filter by genre.
     */
    public function scopeForGenre($query, $genre)
    {
        return $query->when($genre, function ($query, $genre) {
            $query->whereRelation('athleteCategory', 'genre', $genre);
        });
    }

    /**
     * Scope a query to sort by performance using the normalized value.
     */
    public function scopeOrderedByPerformance($query, $direction = 'asc')
    {
        return $query->orderBy('performance_normalized', $direction);
    }

    /**
     * Scope a query to get only the best results per athlete.
     * Note: This usually requires manual collection unique() or a complex subquery.
     * For now, we'll keep the logic consistent with unique() but encapsulate it if possible.
     */
    public function scopeWithRelations($query)
    {
        return $query->with(['athlete', 'athleteCategory', 'event']);
    }

    /**
     * Get the athlete that owns the result.
     */
    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class);
    }

    /**
     * Get the athlete category that owns the result.
     */
    public function athleteCategory(): BelongsTo
    {
        return $this->belongsTo(AthleteCategory::class);
    }

    /**
     * Get the event that owns the result.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the discipline that owns the result.
     */
    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
    }

    /**
     * Get diagnostics for this result.
     * Returns an array of issues found.
     */
    public function getDiagnostics(): array
    {
        $issues = [];

        // 1. Genre mismatch
        if ($this->athlete->genre !== $this->athleteCategory->genre) {
            $issues[] = [
                'type' => 'genre_mismatch',
                'label' => "Genre {$this->athlete->genre} ≠ Cat {$this->athleteCategory->genre}",
                'severity' => 'warning',
            ];
        }

        // 2. Age category mismatch (Athletic Age: Year-based)
        $athleticAge = $this->event->date->year - $this->athlete->birthdate->year;
        $hasValidBirthdate = $this->athlete->birthdate->year > 1900;

        if (!$hasValidBirthdate) {
            $issues[] = [
                'type' => 'missing_birthdate',
                'label' => "Date de naissance manquante",
                'severity' => 'warning',
            ];
        } elseif ($this->athleteCategory->age_limit) {
            // Check if it's an "exact age" category (e.g. U10 W08, U16 M15)
            // Pattern: name ends with [MW]\d{2}
            $isExactAge = preg_match('/[MW]\d{2}$/', $this->athleteCategory->name);

            if ($isExactAge) {
                if ($athleticAge != $this->athleteCategory->age_limit) {
                    $issues[] = [
                        'type' => 'age_mismatch',
                        'label' => "Âge ({$athleticAge} ans) ≠ {$this->athleteCategory->age_limit} attendu",
                        'severity' => 'warning',
                    ];
                }
            } else {
                // Regular "Under" category
                if ($athleticAge > $this->athleteCategory->age_limit) {
                    $issues[] = [
                        'type' => 'age_mismatch',
                        'label' => "Âge ({$athleticAge} ans) > Limite {$this->athleteCategory->age_limit}",
                        'severity' => 'warning',
                    ];
                }
            }
        }

        // 3. Potential duplicate
        // Search for another result with same athlete, discipline and date
        $duplicate = self::where('athlete_id', $this->athlete_id)
            ->where('discipline_id', $this->discipline_id)
            ->where('id', '!=', $this->id)
            ->whereHas('event', function ($query) {
                $query->where('date', $this->event->date);
            })
            ->exists();

        if ($duplicate) {
            $issues[] = [
                'type' => 'duplicate',
                'label' => "Doublon potentiel (même jour)",
                'severity' => 'info',
            ];
        }

        // 4. Performance format check (simple heuristic)
        // If discipline has 'sorting' = asc (usually time), check if it contains ':' or '.' and looks like a number
        // This is a basic check and could be refined per discipline if needed.
        if (!preg_match('/^\d+([.:]\d+)*$/', $this->performance)) {
             $issues[] = [
                'type' => 'format_issue',
                'label' => "Format performance suspect: '{$this->performance}'",
                'severity' => 'info',
            ];
        }

        return $issues;
    }
}
