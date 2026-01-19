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
}
