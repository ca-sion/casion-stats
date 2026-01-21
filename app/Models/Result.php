<?php

namespace App\Models;

use App\Services\DataDiagnosticService;
use App\Support\PerformanceNormalizer;
use App\Traits\HasIaafPoints;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Result extends Model
{
    use HasFactory, HasIaafPoints, PerformanceNormalizer;

    /**
     * Store temporary diagnostics for the model.
     *
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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'performance_normalized' => 'float',
        ];
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::observe(\App\Observers\ResultObserver::class);
    }

    /**
     * Scope a query to filter by discipline.
     */
    public function scopeForDiscipline(Builder $query, $disciplineId): Builder
    {
        return $query->when($disciplineId, fn ($q) => $q->where('discipline_id', $disciplineId));
    }

    /**
     * Scope a query to filter by athlete category.
     */
    public function scopeForCategory(Builder $query, $categoryId, bool $inclusive = false): Builder
    {
        return $query->when($categoryId, function (Builder $query, $categoryId) use ($inclusive) {
            if (! $inclusive) {
                $id = $categoryId instanceof AthleteCategory ? $categoryId->id : $categoryId;

                return $query->where('athlete_category_id', $id);
            }

            // In inclusive mode, we ideally want the category object to avoid a find() here
            // but if only ID is provided, we fetch it once.
            $category = $categoryId instanceof AthleteCategory ? $categoryId : AthleteCategory::find($categoryId);

            if (! $category || $category->age_limit === null || $category->age_limit == 99) {
                return $query->where('athlete_category_id', $categoryId instanceof AthleteCategory ? $category->id : $categoryId);
            }

            return $query->whereHas('athleteCategory', fn (Builder $q) => $q->where('genre', $category->genre)
                ->where('age_limit', '<=', $category->age_limit)
            );
        });
    }

    /**
     * Scope a query to filter by genre.
     */
    public function scopeForGenre(Builder $query, ?string $genre): Builder
    {
        return $query->when($genre, fn (Builder $query, ?string $genre) => $query->whereRelation('athleteCategory', 'genre', $genre)
        );
    }

    /**
     * Scope a query to sort by performance using the normalized value.
     */
    public function scopeOrderedByPerformance(Builder $query, string $direction = 'asc'): Builder
    {
        return $query->orderBy('performance_normalized', $direction);
    }

    /**
     * Scope a query to get only the best results per athlete.
     * Note: This usually requires manual collection unique() or a complex subquery.
     * For now, we'll keep the logic consistent with unique() but encapsulate it if possible.
     */
    public function scopeWithRelations(Builder $query): Builder
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
    protected function athleteAge(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->athlete || ! $this->event || $this->athlete->birthdate->year <= 1900) {
                    return null;
                }

                return $this->event->date->year - $this->athlete->birthdate->year;
            }
        );
    }
}
