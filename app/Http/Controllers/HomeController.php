<?php

namespace App\Http\Controllers;

use App\Models\Result;
use Illuminate\View\View;
use App\Models\Discipline;
use Illuminate\Http\Request;
use App\Models\AthleteCategory;
use Illuminate\Contracts\Database\Query\Builder;

class HomeController extends Controller
{
    /**
     * Show the home.
     */
    public function show(): View
    {
        $d = request()->input('d', 100);
        $ac = request()->input('ac');
        $g = request()->input('g');

        $discipline = Discipline::find($d);

        $resultsOrdered = Result::with(['athlete', 'athleteCategory', 'event'])
            ->where('discipline_id', $discipline->id)
            ->when($ac, function (Builder $query, int $ac) {
                $query->where('athlete_category_id', '=', $ac);
            })
            ->when($g, function (Builder $query, string $g) {
                $query->whereRelation('athleteCategory', 'genre', '=', $g);
            })
            ->orderByRaw('CAST(performance as UNSIGNED) '.$discipline->sorting)
            ->orderBy('performance', $discipline->sorting)
            ->get();

        $results = $resultsOrdered->unique('athlete_id');

        return view('home', [
            'disciplines' => Discipline::all(),
            'athleteCategories' => AthleteCategory::orderBy('order')->get(),
            'results' => $results,
            'd' => $d,
            'ac' => $ac,
            'g' => $g,
        ]);
    }
}
