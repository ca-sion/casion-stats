<?php

namespace App\Livewire;

use App\Models\Event;
use App\Services\EventDeduplicationService;
use Livewire\Component;

class DeduplicateEvents extends Component
{
    public $clusters = [];

    public $scanComplete = false;

    protected $service;

    public function boot(EventDeduplicationService $service)
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
        set_time_limit(120);
        $rawClusters = $this->service->findDuplicates();

        $this->clusters = $rawClusters->map(function ($cluster) {
            return collect($cluster)->map(function ($event) {
                return [
                    'id' => $event->id,
                    'name' => $event->name,
                    'date' => $event->date->format('Y-m-d'),
                    'location' => $event->location ?? '-',
                    'results_count' => \App\Models\Result::where('event_id', $event->id)->count(),
                    'created_at' => $event->created_at ? $event->created_at->format('d.m.Y') : '-',
                ];
            })->toArray();
        })->toArray();

        $this->scanComplete = true;
    }

    public function merge(int $primaryId, int $secondaryId, int $clusterIndex)
    {
        $primary = Event::find($primaryId);
        $secondary = Event::find($secondaryId);

        if ($primary && $secondary) {
            $this->service->mergeEvents($primary, $secondary);

            // Remove the merged event from the cluster in the UI
            $this->clusters[$clusterIndex] = array_filter($this->clusters[$clusterIndex], function ($event) use ($secondaryId) {
                return $event['id'] !== $secondaryId;
            });

            // If only one (or zero) event left in cluster, remove cluster
            if (count($this->clusters[$clusterIndex]) < 2) {
                unset($this->clusters[$clusterIndex]);
            }
        }
    }

    public function ignore(int $clusterIndex)
    {
        unset($this->clusters[$clusterIndex]);
    }

    public function render()
    {
        return view('livewire.deduplicate-events')
            ->layout('components.layouts.app');
    }
}
