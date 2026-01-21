<?php

namespace App\Livewire;

use App\Models\AthleteCategory;
use App\Models\Discipline;
use App\Models\Result;
use Livewire\Attributes\Url;
use Livewire\Component;

class StatsTable extends Component
{
    #[Url(as: 'd')]
    public $disciplineId = null;

    #[Url(as: 'ac')]
    public $categoryId = null;

    #[Url(as: 'g')]
    public $genre = null;

    public $fix = false;

    public $showOnlyErrors = false;

    public $showSql = false;

    #[Url(as: 'inc')]
    public $inclusiveCategory = false;

    public function mount()
    {
        if (app()->isLocal()) {
            $this->fix = session()->get('fix', false);
            $this->showOnlyErrors = session()->get('showOnlyErrors', false);
        } else {
            $this->fix = false;
            $this->showOnlyErrors = false;
        }
    }

    public function updatedFix($value)
    {
        session()->put('fix', (bool) $value);
    }

    public function updatedShowOnlyErrors($value)
    {
        session()->put('showOnlyErrors', (bool) $value);
    }

    public function toggleSql()
    {
        $this->showSql = ! $this->showSql;
    }

    protected function ensureCanFix()
    {
        if (! app()->isLocal()) {
            abort(403, 'Action autorisée uniquement en environnement local.');
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

    public function bulkFix($fixTypes = [])
    {
        $this->ensureCanFix();

        // We fetch the current results based on existing filters
        $results = $this->getResults();

        foreach ($results as $result) {
            // Ensure diagnostics are loaded for the result
            $result->diagnostics = $result->getDiagnostics();

            foreach ($result->diagnostics as $diagnostic) {
                // Only process types that were selected (or all if none specified)
                if (! empty($fixTypes) && ! in_array($diagnostic['type'], $fixTypes)) {
                    continue;
                }

                if ($diagnostic['type'] === 'genre_mismatch') {
                    $result->athlete->update(['genre' => $result->athleteCategory->genre]);
                } elseif ($diagnostic['type'] === 'duplicate' || $diagnostic['type'] === 'missing_relation') {
                    $result->delete();
                } elseif ($diagnostic['type'] === 'age_mismatch' && isset($diagnostic['suggested_category_id'])) {
                    $result->update(['athlete_category_id' => $diagnostic['suggested_category_id']]);
                }
            }
        }

        session()->flash('bulk_success', 'Corrections appliquées avec succès.');
    }

    private function getResults()
    {
        $discipline = Discipline::find($this->disciplineId) ?? Discipline::orderBy('order')->first();
        if ($discipline && $this->disciplineId === null) {
            $this->disciplineId = $discipline->id;
        }

        $category = $this->categoryId ? AthleteCategory::find($this->categoryId) : null;

        $query = Result::query()
            ->withRelations()
            ->forDiscipline($this->disciplineId)
            ->forCategory($category ?: $this->categoryId, $this->inclusiveCategory)
            ->forGenre($this->genre)
            ->orderedByPerformance($discipline->sorting ?? 'asc');

        // On récupère les résultats sans limite SQL trop restrictive pour garantir un vrai Top 100
        // après le filtre unique('athlete_id') en PHP.
        // On limite à 5000 pour éviter des problèmes de mémoire si la base est énorme.
        $results = $query->limit(5000)->get();

        // If not in fix mode, we only want the best result per athlete (vraie limite Top 100)
        if (! $this->fix) {
            $results = $results->unique('athlete_id')->take(100);
        }

        return $results;
    }

    public function render()
    {
        $discipline = Discipline::find($this->disciplineId);
        $results = $this->getResults();

        $errorCount = 0;
        $fixSummary = [
            'genre_mismatch' => 0,
            'duplicate' => 0,
            'age_mismatch' => 0,
            'missing_relation' => 0,
        ];

        // Process diagnostics for results
        foreach ($results as $result) {
            $diagnostics = $result->getDiagnostics();
            $result->diagnostics = $diagnostics;

            if (! empty($diagnostics)) {
                $errorCount++;
                foreach ($diagnostics as $d) {
                    if (isset($fixSummary[$d['type']])) {
                        // Only count if it's actually auto-fixable
                        if ($d['type'] === 'age_mismatch' && ! isset($d['suggested_category_id'])) {
                            continue;
                        }
                        $fixSummary[$d['type']]++;
                    }
                }
            }
        }

        // After calculating diagnostics, if showOnlyErrors is true, we filter them
        if ($this->fix && $this->showOnlyErrors) {
            $results = $results->filter(fn ($r) => ! empty($r->diagnostics));
        }

        // Re-apply unique AFTER diagnostics if in fix mode but user still wants unique athletes?
        // Usually, in fix mode, we might want to see all errors.
        // But the user complained about duplicates of athletes, so let's keep it clean.
        if ($this->fix && ! $this->showOnlyErrors) {
            $results = $results->unique('athlete_id');
        }

        return view('livewire.stats-table', [
            'results' => $results,
            'isFix' => $this->fix && app()->isLocal(),
            'canFix' => app()->isLocal(),
            'errorCount' => $errorCount,
            'disciplines' => Discipline::orderBy('order')->get()->values(),
            'athleteCategories' => AthleteCategory::orderBy('order')->get(),
            'fixSummary' => $fixSummary,
        ]);
    }
}
