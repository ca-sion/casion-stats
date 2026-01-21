<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AthleteResource;
use App\Models\Athlete;
use Dedoc\Scramble\Attributes\QueryParameter;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AthleteController extends Controller
{
    /**
     * Display a listing of the athletes.
     */
    #[QueryParameter('filter[first_name]', description: 'Filter by first name.', type: 'string')]
    #[QueryParameter('filter[last_name]', description: 'Filter by last name.', type: 'string')]
    #[QueryParameter('filter[genre]', description: 'Filter by genre (m/w).', type: 'string')]
    #[QueryParameter('filter[search]', description: 'Search by first or last name.', type: 'string')]
    #[QueryParameter('filter[category]', description: 'Comma-separated list of categories (e.g. U16M, U16W).', type: 'string')]
    #[QueryParameter('sort', description: 'Sort items (e.g. first_name, -last_name, created_at).', type: 'string')]
    #[QueryParameter('include', description: 'Comma-separated relationships to include (e.g. results, personalBests, results.discipline).', type: 'string')]
    #[QueryParameter('page[number]', description: 'The page number.', type: 'int')]
    #[QueryParameter('page[size]', description: 'The number of items per page (default 15).', type: 'int', default: 15)]
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
    #[QueryParameter('include', description: 'Comma-separated relationships (e.g. results, personalBests).', type: 'string')]
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
