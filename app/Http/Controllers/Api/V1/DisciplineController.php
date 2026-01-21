<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\DisciplineResource;
use App\Models\Discipline;
use Dedoc\Scramble\Attributes\QueryParameter;
use Spatie\QueryBuilder\QueryBuilder;

class DisciplineController extends Controller
{
    /**
     * Display a listing of the disciplines.
     */
    #[QueryParameter('sort', description: 'Sort items (e.g. name).', type: 'string')]
    #[QueryParameter('page[number]', description: 'The page number.', type: 'int')]
    #[QueryParameter('page[size]', description: 'The number of items per page.', type: 'int')]
    public function index()
    {
        $disciplines = QueryBuilder::for(Discipline::class)
            ->allowedSorts(['name'])
            ->get();

        return DisciplineResource::collection($disciplines);
    }
}
