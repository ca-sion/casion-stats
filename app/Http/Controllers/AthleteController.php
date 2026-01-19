<?php

namespace App\Http\Controllers;

use App\Models\Athlete;
use Illuminate\View\View;

class AthleteController extends Controller
{
    public function show(Athlete $athlete): View
    {
        $athlete->load(['results' => function ($query) {
            $query->with(['discipline', 'event', 'athleteCategory'])
                  ->orderBy('performance_normalized', 'asc');
        }]);

        // Get personal bests per discipline
        $personalBests = $athlete->results
            ->whereNotNull('performance_normalized')
            ->groupBy('discipline_id')
            ->map(function ($results) {
                $discipline = $results->first()->discipline;
                $direction = strtolower($discipline->sorting ?? 'asc');
                
                // For 'asc' (time), we want the minimum value
                // For 'desc' (distance), we want the maximum value
                return ($direction === 'asc') 
                    ? $results->sortBy('performance_normalized')->first()
                    : $results->sortByDesc('performance_normalized')->first();
            });

        return view('athletes.show', [
            'athlete' => $athlete,
            'personalBests' => $personalBests,
            'results' => $athlete->results->sortByDesc(fn($r) => $r->event->date),
        ]);
    }
}
