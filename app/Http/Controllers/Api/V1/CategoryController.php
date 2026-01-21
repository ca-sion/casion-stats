<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\CategoryResource;
use App\Models\AthleteCategory;
use Dedoc\Scramble\Attributes\QueryParameter;
use Spatie\QueryBuilder\QueryBuilder;

class CategoryController extends Controller
{
    /**
     * Display a listing of the categories.
     */
    #[QueryParameter('filter[genre]', description: 'Filter by genre (m/w).', type: 'string')]
    #[QueryParameter('sort', description: 'Sort items (e.g. order, name).', type: 'string')]
    #[QueryParameter('page[number]', description: 'The page number.', type: 'int')]
    #[QueryParameter('page[size]', description: 'The number of items per page.', type: 'int')]
    public function index()
    {
        $categories = QueryBuilder::for(AthleteCategory::class)
            ->allowedFilters(['genre'])
            ->allowedSorts(['order', 'name'])
            ->get();

        return CategoryResource::collection($categories);
    }
}
