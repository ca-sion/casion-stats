<?php

namespace App\Livewire;

use App\Models\Discipline;
use App\Models\Result;
use App\Services\DisciplineDeduplicationService;
use Livewire\Component;
use Livewire\WithPagination;

class DeduplicateDisciplines extends Component
{
    use WithPagination;

    public $search = '';

    public $selectedIds = [];

    public $isMergeModalOpen = false;

    public $targetId = null;

    protected $service;

    public function boot(DisciplineDeduplicationService $service)
    {
        $this->service = $service;
    }

    public function mount()
    {
        if (! app()->isLocal()) {
            abort(403, 'Accès réservé à l\'environnement local.');
        }
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function toggleSelection(int $id)
    {
        if (in_array($id, $this->selectedIds)) {
            $this->selectedIds = array_diff($this->selectedIds, [$id]);
        } else {
            $this->selectedIds[] = $id;
        }
    }

    public function openMergeModal()
    {
        if (count($this->selectedIds) < 2) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Sélectionnez au moins 2 disciplines.']);

            return;
        }
        $this->targetId = $this->selectedIds[0];
        $this->isMergeModalOpen = true;
    }

    public function closeMergeModal()
    {
        $this->isMergeModalOpen = false;
    }

    public function confirmMerge()
    {
        $target = Discipline::find($this->targetId);
        $sourceIds = array_diff($this->selectedIds, [$this->targetId]);

        if ($target && ! empty($sourceIds)) {
            $this->service->mergeDisciplines($target, $sourceIds);
            $this->selectedIds = [];
            $this->isMergeModalOpen = false;
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Fusion terminée avec succès.']);
        }
    }

    public function deleteIfEmpty(int $id)
    {
        $discipline = Discipline::find($id);
        if ($discipline) {
            $count = Result::where('discipline_id', $id)->count();
            if ($count === 0) {
                $discipline->delete();
                $this->dispatch('notify', ['type' => 'success', 'message' => 'Discipline supprimée (0 résultats).']);
            } else {
                $this->dispatch('notify', ['type' => 'error', 'message' => 'Impossible : cette discipline a des résultats.']);
            }
        }
    }

    public function getSelectedDisciplinesProperty()
    {
        return Discipline::whereIn('id', $this->selectedIds)
            ->get()
            ->map(function ($discipline) {
                $discipline->results_count = Result::where('discipline_id', $discipline->id)->count();

                return $discipline;
            });
    }

    public function render()
    {
        $disciplines = Discipline::query()
            ->when($this->search, function ($query) {
                $query->where('name_fr', 'like', '%'.$this->search.'%')
                    ->orWhere('name_de', 'like', '%'.$this->search.'%')
                    ->orWhere('code', 'like', '%'.$this->search.'%')
                    ->orWhere('wa_code', 'like', '%'.$this->search.'%')
                    ->orWhere('seltec_code', 'like', '%'.$this->search.'%')
                    ->orWhere('seltec_id', 'like', '%'.$this->search.'%')
                    ->orWhere('alabus_id', 'like', '%'.$this->search.'%');
            })
            ->orderBy('name_fr')
            ->paginate(10);

        // Enhance items for display
        $disciplines->getCollection()->transform(function ($discipline) {
            $discipline->results_count = Result::where('discipline_id', $discipline->id)->count();
            $discipline->samples = Result::where('discipline_id', $discipline->id)
                ->with(['athlete', 'event'])
                ->latest()
                ->limit(3)
                ->get()
                ->map(function ($result) {
                    return [
                        'athlete_name' => $result->athlete ? ($result->athlete->first_name.' '.$result->athlete->last_name) : 'Inconnu',
                        'performance' => $result->performance,
                        'date' => $result->event ? $result->event->date->format('d.m.Y') : '-',
                    ];
                });

            return $discipline;
        });

        return view('livewire.deduplicate-disciplines', [
            'disciplines' => $disciplines,
            'selectedDisciplines' => $this->selected_disciplines,
        ])->layout('components.layouts.app');
    }
}
