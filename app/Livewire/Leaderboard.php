<?php

namespace App\Livewire;

use App\Models\AthleteCategory;
use App\Models\Result;
use Livewire\Component;

class Leaderboard extends Component
{
    public $genre = '';
    public $categoryId = '';
    public $limit = 100;
    public $readyToLoad = false;

    public function loadData()
    {
        $this->readyToLoad = true;
    }

    public function render()
    {
        $results = collect();
        
        if ($this->readyToLoad) {
            // Perform real-time SQL ranking for sub-200ms performance
            // Step 1: Find the best iaaf_points per athlete in the database
            $subQuery = Result::selectRaw('athlete_id, MAX(iaaf_points) as best_points')
                ->whereNotNull('iaaf_points');

            if ($this->genre) {
                $subQuery->forGenre($this->genre);
            }

            if ($this->categoryId) {
                $subQuery->forCategory($this->categoryId);
            }

            $topAthleteBestPoints = $subQuery->groupBy('athlete_id')
                ->orderByDesc('best_points')
                ->limit(500) 
                ->get();

            if ($topAthleteBestPoints->isEmpty()) {
                $results = collect();
            } else {
                // Step 2: Fetch the full result records for these athlete bests
                $finalResults = collect();
                foreach ($topAthleteBestPoints as $best) {
                    $bestResult = Result::with(['athlete', 'discipline', 'athleteCategory'])
                        ->where('athlete_id', $best->athlete_id)
                        ->where('iaaf_points', $best->best_points)
                        ->first();
                    
                    if ($bestResult) {
                        $finalResults->push($bestResult);
                    }
                }
                $results = $finalResults;
            }
        }

        return view('livewire.leaderboard', [
            'results' => $results->take($this->limit),
            'categories' => AthleteCategory::orderBy('order')->get(),
        ])->layoutData(['title' => 'Leaderboard 🏆 - CA Sion Stats']);
    }

    public function showMore()
    {
        $this->limit += 100;
    }

    public function setGenre($genre)
    {
        $this->genre = $genre;
        $this->loadData(); // Trigger reload when filters change
    }

    public function updatedCategoryId()
    {
        $this->loadData();
    }
}
