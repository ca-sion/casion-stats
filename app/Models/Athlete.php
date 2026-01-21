<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Athlete extends Model
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
        'birthdate' => 'date',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'birthdate',
    ];

    /**
     * Get the results for the athlete.
     */
    public function results(): HasMany
    {
        return $this->hasMany(Result::class);
    }

    /**
     * Proxy relation for results to allow independent inclusion of personal bests.
     */
    public function personalBests(): HasMany
    {
        return $this->results();
    }

    /**
     * Get the athlete's current category based on age and genre.
     */
    protected function currentCategory(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->birthdate || ! $this->birthdate instanceof CarbonInterface) {
                    return null;
                }
                $age = now()->year - $this->birthdate->year;

                return AthleteCategory::where('genre', $this->genre)
                    ->where(function ($q) use ($age) {
                        $q->where('age_limit', '>=', $age)
                            ->orWhere('age_limit', 99);
                    })
                    ->orderBy('age_limit', 'asc')
                    ->first();
            }
        );
    }

    /**
     * Get the year of the athlete's first result.
     */
    protected function activityStart(): Attribute
    {
        return Attribute::make(
            get: function () {
                $minDate = $this->results()->with('event')->get()->min('event.date');

                return $minDate instanceof CarbonInterface ? $minDate->year : null;
            }
        );
    }

    /**
     * Get the year of the athlete's last result.
     */
    protected function activityEnd(): Attribute
    {
        return Attribute::make(
            get: function () {
                $maxDate = $this->results()->with('event')->get()->max('event.date');

                return $maxDate instanceof CarbonInterface ? $maxDate->year : null;
            }
        );
    }

    /**
     * Scope a query to filter athletes by category names.
     */
    public function scopeInCategories(Builder $query, array $categoryNames): void
    {
        $allCategories = AthleteCategory::orderBy('age_limit')->get();
        $categoryNames = array_map('trim', $categoryNames);

        $query->where(function ($q) use ($categoryNames, $allCategories) {
            $hasCondition = false;

            foreach ($categoryNames as $name) {
                // 1. Try exact match
                $category = $allCategories->first(fn ($c) => strcasecmp($c->name, $name) === 0);

                // 2. Fallback: try adding space (e.g. "U16M" -> "U16 M")
                if (! $category && preg_match('/^([A-Z0-9]+)([MW])$/i', $name, $m)) {
                    $category = $allCategories->first(fn ($c) => strcasecmp($c->name, $m[1].' '.$m[2]) === 0);
                }

                if (! $category) {
                    continue;
                }

                // Find immediate lower category to define the range
                $lowerCategory = $allCategories->where('genre', $category->genre)
                    ->where('age_limit', '<', $category->age_limit)
                    ->sortByDesc('age_limit')
                    ->first();

                $minAge = $lowerCategory ? $lowerCategory->age_limit + 1 : 0;
                $maxAge = $category->age_limit;

                $currentYear = now()->year;
                $minBirthYear = $currentYear - $maxAge;
                $maxBirthYear = $currentYear - $minAge;

                $q->orWhere(function ($sub) use ($category, $minBirthYear, $maxBirthYear) {
                    $sub->where('genre', $category->genre)
                        ->whereYear('birthdate', '>=', $minBirthYear)
                        ->whereYear('birthdate', '<=', $maxBirthYear);
                });
                $hasCondition = true;
            }

            if (! $hasCondition) {
                $q->whereRaw('1 = 0');
            }
        });
    }
}
