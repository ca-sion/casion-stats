<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Result extends Model
{
    use HasFactory;
    
    /**
     * Store temporary diagnostics for the model.
     * @var array|null
     */
    public $diagnostics = null;

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
    public function scopeForCategory($query, $categoryId, $inclusive = false)
    {
        return $query->when($categoryId, function ($query, $categoryId) use ($inclusive) {
            if (!$inclusive) {
                return $query->where('athlete_category_id', $categoryId);
            }

            $category = AthleteCategory::find($categoryId);
            if (!$category || $category->age_limit === null || $category->age_limit == 99) {
                return $query->where('athlete_category_id', $categoryId);
            }

            return $query->whereHas('athleteCategory', function ($q) use ($category) {
                $q->where('genre', $category->genre)
                  ->where('age_limit', '<=', $category->age_limit);
            });
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
                'sql_fix' => "UPDATE athletes SET genre = '{$this->athleteCategory->genre}' WHERE id = {$this->athlete->id};",
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
                'sql_fix' => "UPDATE athletes SET birthdate = 'YYYY-MM-DD' WHERE id = {$this->athlete->id};",
            ];
        } elseif ($this->athleteCategory->age_limit) {
            // Check if it's an "exact age" category (e.g. U10 W08, U16 M15)
            // Pattern: name ends with [MW]\d{2}
            $isExactAge = preg_match('/[MW]\d{2}$/', $this->athleteCategory->name);

            if ($isExactAge) {
                if ($athleticAge != $this->athleteCategory->age_limit) {
                    $suggestedCategory = AthleteCategory::where('genre', $this->athlete->genre)
                        ->where('name', 'LIKE', '% ' . ($athleticAge < 10 ? '0' : '') . $athleticAge)
                        ->get()
                        ->sortBy(fn($cat) => preg_match('/\d{2}$/', $cat->name)) // Prioritize those WITHOUT age at the end
                        ->first();

                    $issues[] = [
                        'type' => 'age_mismatch',
                        'label' => "Âge ({$athleticAge} ans) ≠ {$this->athleteCategory->age_limit} attendu",
                        'severity' => 'warning',
                        'suggested_category_id' => $suggestedCategory?->id,
                        'sql_fix' => $suggestedCategory 
                            ? "UPDATE results SET athlete_category_id = {$suggestedCategory->id} WHERE id = {$this->id};"
                            : null,
                    ];
                }
            } else {
                // Regular "Under" category
                if ($athleticAge > $this->athleteCategory->age_limit) {
                    // Find the smallest limit that is >= athletic age
                    $suggestedCategory = AthleteCategory::where('genre', $this->athlete->genre)
                        ->where('age_limit', '>=', $athleticAge)
                        ->orderBy('age_limit', 'asc')
                        ->get()
                        ->sortBy(fn($cat) => preg_match('/\d{2}$/', $cat->name)) // Prioritize general categories (U18 M) over specific ones (U16 M15)
                        ->first();
                    
                    if (!$suggestedCategory) {
                        // Fallback to MAN/WOM if older than all limits
                        $suggestedCategory = AthleteCategory::where('genre', $this->athlete->genre)
                            ->where('age_limit', 99)
                            ->first();
                    }

                    $issues[] = [
                        'type' => 'age_mismatch',
                        'label' => "Âge ({$athleticAge} ans) > Limite {$this->athleteCategory->age_limit}",
                        'severity' => 'warning',
                        'suggested_category_id' => $suggestedCategory?->id,
                        'sql_fix' => $suggestedCategory 
                            ? "UPDATE results SET athlete_category_id = {$suggestedCategory->id} WHERE id = {$this->id};"
                            : null,
                    ];
                } else {
                    // Check if a better general category exists for this age (Optimization)
                    // We only suggest moving to a General category (no digits at end)
                    $optimalCategory = AthleteCategory::where('genre', $this->athlete->genre)
                        ->where('age_limit', '>=', $athleticAge)
                        ->where('age_limit', '<=', $this->athleteCategory->age_limit)
                        ->get()
                        ->filter(fn($cat) => !preg_match('/\d{2}$/', $cat->name)) // Must be General
                        ->sortBy('age_limit')
                        ->first();

                    if ($optimalCategory && $optimalCategory->id !== $this->athlete_category_id) {
                        $issues[] = [
                            'type' => 'age_mismatch',
                            'label' => "Cat {$this->athleteCategory->name} alors que {$optimalCategory->name} possible ({$athleticAge} ans)",
                            'severity' => 'info',
                            'suggested_category_id' => $optimalCategory->id,
                            'sql_fix' => "UPDATE results SET athlete_category_id = {$optimalCategory->id} WHERE id = {$this->id};",
                        ];
                    }
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
                'sql_fix' => "DELETE FROM results WHERE id = {$this->id};",
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
                'sql_fix' => "UPDATE results SET performance = '{$this->performance}' WHERE id = {$this->id};", // User will need to edit '{$this->performance}'
            ];
        }

        return $issues;
    }
}
