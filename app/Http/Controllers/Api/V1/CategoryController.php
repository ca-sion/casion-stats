<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\CategoryResource;
use App\Models\AthleteCategory;
use Spatie\QueryBuilder\QueryBuilder;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = QueryBuilder::for(AthleteCategory::class)
            ->allowedFilters(['genre'])
            ->allowedSorts(['order', 'name'])
            ->get();

        return CategoryResource::collection($categories);
    }
}
