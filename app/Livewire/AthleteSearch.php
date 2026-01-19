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
            $this->results = Athlete::where('first_name', 'like', '%' . $this->query . '%')
                ->orWhere('last_name', 'like', '%' . $this->query . '%')
                ->take(7)
                ->get();
        }
    }

    public function render()
    {
        return view('livewire.athlete-search');
    }
}
