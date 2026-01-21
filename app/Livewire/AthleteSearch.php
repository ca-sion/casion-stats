<?php

namespace App\Livewire;

use App\Models\Athlete;
use Livewire\Component;

class AthleteSearch extends Component
{
    public $query = '';

    public $results = [];

    public function updatedQuery()
    {
        $this->results = [];

        if (strlen($this->query) >= 2) {
            $terms = explode(' ', $this->query);

            $queryBuilder = Athlete::query();

            foreach ($terms as $term) {
                if (empty($term)) {
                    continue;
                }
                $queryBuilder->where(function ($q) use ($term) {
                    $q->where('first_name', 'like', '%'.$term.'%')
                        ->orWhere('last_name', 'like', '%'.$term.'%');
                });
            }

            $this->results = $queryBuilder->take(7)->get();
        }
    }

    public function render()
    {
        return view('livewire.athlete-search');
    }
}
