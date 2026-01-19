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

    public function mount()
    {
        $this->fix = session()->get('fix', false);
    }

    public function updatedFix($value)
    {
        session()->put('fix', (bool)$value);
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

        return view('livewire.stats-table', [
            'results' => $results,
            'isFix' => $isFix,
            'disciplines' => Discipline::all(),
            'athleteCategories' => AthleteCategory::orderBy('order')->get(),
        ]);
    }
}
