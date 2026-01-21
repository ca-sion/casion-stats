<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AthleteResource;
use App\Models\Athlete;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AthleteController extends Controller
{
    /**
     * Display a listing of the athletes.
     */
    public function index()
    {
        $athletes = QueryBuilder::for(Athlete::class)
            ->allowedFilters([
                'first_name',
                'last_name',
                'genre',
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($query) use ($value) {
                        $query->where('first_name', 'like', "%{$value}%")
                            ->orWhere('last_name', 'like', "%{$value}%");
                    });
                }),
                AllowedFilter::callback('category', function ($query, $value) {
                    $categoryNames = is_array($value) ? $value : explode(',', $value);
                    $query->inCategories($categoryNames);
                }),
            ])
            ->allowedIncludes([
                'results', 
                'personalBests',
                'results.discipline', 
                'results.event', 
                'results.athleteCategory',
                'personalBests.discipline',
                'personalBests.event',
                'personalBests.athleteCategory',
            ])
            ->allowedSorts(['first_name', 'last_name', 'created_at'])
            ->jsonPaginate();

        return AthleteResource::collection($athletes);
    }

    /**
     * Display the specified athlete.
     */
    public function show($id)
    {
        $athlete = QueryBuilder::for(Athlete::class)
            ->allowedIncludes([
                'results', 
                'personalBests',
                'results.discipline', 
                'results.event', 
                'results.athleteCategory',
                'personalBests.discipline',
                'personalBests.event',
                'personalBests.athleteCategory',
            ])
            ->findOrFail($id);

        // Manually append attributes if requested or needed by resource
        $athlete->append(['activity_start', 'activity_end', 'current_category']);

        return new AthleteResource($athlete);
    }
}
