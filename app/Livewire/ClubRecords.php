<?php

namespace App\Livewire;

use App\Models\Discipline;
use App\Models\Result;
use Livewire\Component;

class ClubRecords extends Component
{
    public $genre = '';

    public $readyToLoad = false;

    public function loadData()
    {
        $this->readyToLoad = true;
    }

    public function setGenre($genre)
    {
        $this->genre = $genre;
        $this->loadData();
    }

    public function render()
    {
        $disciplines = collect();
        $recordsByDiscipline = collect();

        if ($this->readyToLoad) {
            // Get all official WA disciplines ordered by 'order' column
            $disciplines = Discipline::where('is_official_wa', true)
                ->orderBy('order')
                ->get();

            // For each discipline, get top 10 athletes
            foreach ($disciplines as $discipline) {
                $records = $this->getRecordsByDiscipline($discipline->id, $this->genre);
                if ($records->isNotEmpty()) {
                    $recordsByDiscipline->put($discipline->id, $records);
                }
            }
        }

        return view('livewire.club-records', [
            'disciplines' => $disciplines,
            'recordsByDiscipline' => $recordsByDiscipline,
        ])->layoutData(['title' => 'Records du Club 🏆 - CA Sion Stats']);
    }

    private function getRecordsByDiscipline($disciplineId, $genre)
    {
        $discipline = Discipline::find($disciplineId);
        if (!$discipline) {
            return collect();
        }

        // Get the sorting direction from the discipline (same as StatsTable)
        $sortDirection = $discipline->sorting ?? 'asc';

        // Get all results for this discipline, sorted by performance
        $query = Result::query()
            ->with(['athlete', 'event'])
            ->where('discipline_id', $disciplineId)
            ->whereNotNull('performance_normalized')
            ->orderedByPerformance($sortDirection);

        if ($genre) {
            $query->forGenre($genre);
        }

        // Get top results (limit high to ensure we get 10 unique athletes)
        $results = $query->limit(50000)->get();

        // Keep only best result per athlete, then take top 10
        return $results->unique('athlete_id')->take(10);
    }
}
