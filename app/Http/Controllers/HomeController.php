<?php

namespace App\Http\Controllers;

use App\Models\AthleteCategory;
use App\Models\Discipline;
use App\Models\Result;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Show the home.
     */
    public function show(): View
    {
        // Inputs
        $d = request()->input('d', 100);
        $ac = request()->input('ac');
        $g = request()->input('g');
        $fix = request()->input('fix');

        // Fix
        if ($fix == 'on') {
            session()->put('fix', true);
        } elseif ($fix == 'off') {
            session()->put('fix', false);
        }
        $isFix = session()->get('fix');

        // Logic
        $discipline = Discipline::find($d);

        $resultsOrdered = Result::withRelations()
            ->forDiscipline($discipline->id)
            ->forCategory($ac)
            ->forGenre($g)
            ->orderedByPerformance($discipline->sorting)
            ->get();

        $results = $resultsOrdered->unique('athlete_id');

        return view('home', [
            'disciplines' => Discipline::all(),
            'athleteCategories' => AthleteCategory::orderBy('order')->get(),
            'results' => $results,
            'd' => $d,
            'ac' => $ac,
            'g' => $g,
            'fix' => $fix,
            'isFix' => $isFix,
        ]);
    }
}
