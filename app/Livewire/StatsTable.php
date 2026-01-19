<?php

namespace App\Livewire;

use App\Models\AthleteCategory;
use App\Models\Discipline;
use App\Models\Result;
use Livewire\Component;
use Livewire\Attributes\Url;

class StatsTable extends Component
{
    #[Url(as: 'd')]
    public $disciplineId = 100;

    #[Url(as: 'ac')]
    public $categoryId = null;

    #[Url(as: 'g')]
    public $genre = null;

    public $fix = false;
    public $showOnlyErrors = false;
    public $showSql = false;

    public function mount()
    {
        $this->fix = session()->get('fix', false);
        $this->showOnlyErrors = session()->get('showOnlyErrors', false);
    }

    public function updatedFix($value)
    {
        session()->put('fix', (bool)$value);
    }

    public function updatedShowOnlyErrors($value)
    {
        session()->put('showOnlyErrors', (bool)$value);
    }

    public function toggleSql()
    {
        $this->showSql = !$this->showSql;
    }

    protected function ensureCanFix()
    {
        if (!app()->isLocal()) {
            abort(403, "Action autorisée uniquement en environnement local.");
        }
    }

    public function syncAthleteGenre($athleteId, $correctGenre)
    {
        $this->ensureCanFix();
        
        $athlete = \App\Models\Athlete::findOrFail($athleteId);
        $athlete->update(['genre' => $correctGenre]);
    }

    public function deleteResult($resultId)
    {
        $this->ensureCanFix();
        
        $result = \App\Models\Result::findOrFail($resultId);
        $result->delete();
    }

    public function changeCategory($resultId, $categoryId)
    {
        $this->ensureCanFix();
        
        $result = \App\Models\Result::findOrFail($resultId);
        $result->update(['athlete_category_id' => $categoryId]);
    }

    public function updatePerformance($resultId, $performance)
    {
        $this->ensureCanFix();
        
        $result = \App\Models\Result::findOrFail($resultId);
        $result->update(['performance' => $performance]);
    }

    public function render()
    {
        $discipline = Discipline::find($this->disciplineId);
        $isFix = $this->fix;

        $resultsOrdered = Result::withRelations()
            ->forDiscipline($this->disciplineId)
            ->forCategory($this->categoryId)
            ->forGenre($this->genre)
            ->orderedByPerformance($discipline->sorting ?? 'asc')
            ->get();

        $results = $resultsOrdered->unique('athlete_id');

        $errorCount = 0;
        if ($isFix) {
            $resultsWithDiagnostics = $results->map(function ($result) {
                $result->diagnostics = $result->getDiagnostics();
                return $result;
            });

            $errorCount = $resultsWithDiagnostics->filter(fn($r) => !empty($r->diagnostics))->count();

            if ($this->showOnlyErrors) {
                $results = $resultsWithDiagnostics->filter(fn($r) => !empty($r->diagnostics));
            } else {
                $results = $resultsWithDiagnostics;
            }
        }

        return view('livewire.stats-table', [
            'results' => $results,
            'isFix' => $isFix,
            'canFix' => app()->isLocal(),
            'errorCount' => $errorCount,
            'disciplines' => Discipline::all(),
            'athleteCategories' => AthleteCategory::orderBy('order')->get(),
        ]);
    }
}
