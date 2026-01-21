<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\DisciplineResource;
use App\Models\Discipline;
use Spatie\QueryBuilder\QueryBuilder;

class DisciplineController extends Controller
{
    public function index()
    {
        $disciplines = QueryBuilder::for(Discipline::class)
            ->allowedSorts(['name'])
            ->get();

        return DisciplineResource::collection($disciplines);
    }
}
