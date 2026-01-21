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
            ->map(function ($results) use ($athlete) {
                $discipline = $results->first()->discipline;
                $direction = strtolower($discipline->sorting ?? 'asc');

                // For 'asc' (time), we want the minimum value
                // For 'desc' (distance), we want the maximum value
                $pb = ($direction === 'asc')
                    ? $results->sortBy('performance_normalized')->first()
                    : $results->sortByDesc('performance_normalized')->first();

                // Calculate Rank in Top 100 for this discipline and genre
                // We count how many DISTINCT athletes have a better PB than this one
                $betterAthletesCount = \App\Models\Result::where('discipline_id', $discipline->id)
                    ->whereHas('athlete', function ($query) use ($athlete) {
                        $query->where('genre', $athlete->genre);
                    })
                    ->where('performance_normalized', $direction === 'asc' ? '<' : '>', $pb->performance_normalized)
                    ->select('athlete_id')
                    ->distinct()
                    ->count();

                $pb->top100_rank = $betterAthletesCount + 1;

                return $pb;
            });

        $years = $athlete->results->map(fn ($r) => $r->event->date->year)->unique()->sort();
        $activityPeriod = $years->count() > 0
            ? ($years->first() == $years->last() ? $years->first() : $years->first().' - '.$years->last())
            : null;

        return view('athletes.show', [
            'athlete' => $athlete,
            'personalBests' => $personalBests->sortBy(fn ($pb) => $pb->discipline->order),
            'results' => $athlete->results->sortByDesc(fn ($r) => $r->event->date),
            'totalPerformances' => $athlete->results->count(),
            'activityPeriod' => $activityPeriod,
        ]);
    }
}
