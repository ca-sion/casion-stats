<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Services\DataDiagnosticService;

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
                $id = $categoryId instanceof AthleteCategory ? $categoryId->id : $categoryId;
                return $query->where('athlete_category_id', $id);
            }

            // In inclusive mode, we ideally want the category object to avoid a find() here
            // but if only ID is provided, we fetch it once.
            $category = $categoryId instanceof AthleteCategory ? $categoryId : AthleteCategory::find($categoryId);
            
            if (!$category || $category->age_limit === null || $category->age_limit == 99) {
                return $query->where('athlete_category_id', $categoryId instanceof AthleteCategory ? $category->id : $categoryId);
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
     * Get diagnostics for this result via the service.
     */
    public function getDiagnostics(): array
    {
        return app(DataDiagnosticService::class)->getDiagnostics($this);
    }
    /**
     * Get the athlete's age at the time of the performance.
     */
    public function getAthleteAgeAttribute()
    {
        if (!$this->athlete || !$this->event || $this->athlete->birthdate->year <= 1900) {
            return null;
        }

        return $this->event->date->year - $this->athlete->birthdate->year;
    }
}
