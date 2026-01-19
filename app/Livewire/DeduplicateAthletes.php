<?php

namespace App\Livewire;

use App\Models\Athlete;
use App\Services\AthleteDeduplicationService;
use Illuminate\Support\Collection;
use Livewire\Component;

class DeduplicateAthletes extends Component
{
    public $clusters = [];
    public $scanComplete = false;
    public $ignoredIds = []; // Pair IDs that user chose to ignore

    protected $service;

    public function boot(AthleteDeduplicationService $service)
    {
        $this->service = $service;
    }

    public function mount()
    {
        if (! app()->isLocal()) {
            abort(403, 'Accès réservé à l\'environnement local.');
        }
    }

    public function scan()
    {
        set_time_limit(120); // Allow 2 minutes for scan
        $rawClusters = $this->service->findDuplicates();
        
        // Transform into array for UI
        $this->clusters = $rawClusters->map(function ($cluster) {
            return collect($cluster)->map(function ($athlete) {
                return [
                    'id' => $athlete->id,
                    'name' => $athlete->first_name . ' ' . $athlete->last_name,
                    'birthdate' => $athlete->birthdate ? $athlete->birthdate->format('Y-m-d') : '-',
                    'license' => $athlete->license ?? '-',
                    'results_count' => $athlete->results()->count(),
                    'created_at' => $athlete->created_at ? $athlete->created_at->format('d.m.Y') : '-',
                ];
            })->toArray();
        })->toArray(); // Array of Arrays of Arrays

        $this->scanComplete = true;
    }

    public function merge(int $primaryId, int $secondaryId, int $clusterIndex)
    {
        $primary = Athlete::find($primaryId);
        $secondary = Athlete::find($secondaryId);

        if ($primary && $secondary) {
            $this->service->mergeAthletes($primary, $secondary);
            
            // Remove the cluster from UI
            unset($this->clusters[$clusterIndex]);
            // Re-indexing is often needed for Livewire arrays keys to work smoothly? 
            // Or we just unset. 
        }
    }

    public function ignore(int $clusterIndex)
    {
        unset($this->clusters[$clusterIndex]);
    }

    public function render()
    {
        return view('livewire.deduplicate-athletes')
            ->layout('components.layouts.app');
    }
}
