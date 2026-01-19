<div class="flex flex-col md:flex-row justify-center gap-6">

    <div>
        <div class="flex flex-col gap-2">
            <div x-data="{
                open: false,
                search: '',
                disciplines: @js($disciplines),
                get filteredDisciplines() {
                    if (this.search === '') return this.disciplines;
                    const s = this.search.toLowerCase();
                    return this.disciplines.filter(d => d.name.toLowerCase().includes(s));
                },
                select(id) {
                    $wire.set('disciplineId', id);
                    this.open = false;
                    this.search = '';
                }
            }" class="dropdown w-full" :class="open && 'dropdown-open'" @click.outside="open = false">
                <div tabindex="0" role="button" class="select select-bordered w-full flex items-center justify-between" @click="open = !open">
                    <span>{{ $disciplines->firstWhere('id', $disciplineId)?->name ?? 'Discipline' }}</span>
                </div>
                <div class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-full mt-1 border border-base-300">
                    <input type="text" x-model="search" placeholder="Rechercher..." class="input input-sm input-bordered mb-2 w-full" autofocus @click.stop x-ref="searchInput" x-effect="if(open) $nextTick(() => $refs.searchInput.focus())">
                    <ul class="max-h-60 overflow-y-auto flex flex-col gap-1">
                        <template x-for="discipline in filteredDisciplines" :key="discipline.id">
                            <li>
                                <a @click="select(discipline.id)" :class="discipline.id == {{ $disciplineId }} && 'menu-active'">
                                    <span x-text="discipline.name"></span>
                                </a>
                            </li>
                        </template>
                        <li x-show="filteredDisciplines.length === 0" class="p-2 text-center text-sm opacity-50">Aucun résultat</li>
                    </ul>
                </div>
            </div>
            <div class="flex flex-col gap-1">
                <select class="select select-bordered w-full" wire:model.live="categoryId">
                    <option value="">Choisir une catégorie</option>
                    @foreach ($athleteCategories as $athleteCategory)
                    <option value="{{ $athleteCategory->id }}">{{ $athleteCategory->name }}</option>
                    @endforeach
                </select>
                @if($categoryId && $athleteCategories->firstWhere('id', $categoryId)?->age_limit < 99)
                <div class="flex items-center gap-2 px-1 py-1 animate-in fade-in duration-300">
                    <input type="checkbox" class="checkbox checkbox-xs" id="inclusiveCategory" wire:model.live="inclusiveCategory" />
                    <label for="inclusiveCategory" class="label-text text-[10px] cursor-pointer opacity-70 hover:opacity-100 transition-opacity">Inclure les plus jeunes</label>
                </div>
                @endif
            </div>
            <select class="select select-bordered w-full" wire:model.live="genre">
                <option value="">Choisir un genre</option>
                <option value="m">Homme</option>
                <option value="w">Femme</option>
            </select>
        </div>

        <div class="flex flex-col gap-2 mt-6">
            <div class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" class="toggle toggle-warning toggle-xs" id="fix" wire:model.live="fix" />
                <label for="fix" class="label-text text-xs cursor-pointer">Mode Diagnostic (Fix)</label>
            </div>
            @if ($isFix)
            <div class="flex items-center gap-2 cursor-pointer animate-in fade-in duration-300">
                <input type="checkbox" class="toggle toggle-info toggle-xs" id="showOnlyErrors" wire:model.live="showOnlyErrors" />
                <label for="showOnlyErrors" class="label-text text-xs cursor-pointer">Erreurs uniquement</label>
            </div>
            <div class="flex items-center gap-2 cursor-pointer animate-in fade-in duration-300">
                <input type="checkbox" class="toggle toggle-secondary toggle-xs" id="showSql" wire:click="toggleSql" />
                <label for="showSql" class="label-text text-xs cursor-pointer">Voir SQL</label>
            </div>
            @endif
        </div>
    </div>

    <div class="overflow-x-auto relative min-w-[600px]">
        @if ($isFix && $errorCount > 0)
        <div class="bg-orange-50 border-l-4 border-orange-400 p-4 mb-4 rounded shadow-sm animate-in slide-in-from-top duration-500">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-orange-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-orange-700">
                            {{ $errorCount }} anomalie{{ $errorCount > 1 ? 's' : '' }} détectée{{ $errorCount > 1 ? 's' : '' }}.
                        </p>
                    </div>
                </div>

                @if($canFix && array_sum($fixSummary) > 0)
                <div class="flex flex-col sm:flex-row items-center gap-3 bg-white/50 p-2 rounded border border-orange-200">
                    <div class="text-[10px] text-orange-800 leading-tight">
                        <strong>Auto-fixes dispos :</strong>
                        <ul class="flex flex-wrap gap-x-2">
                            @if($fixSummary['genre_mismatch'] > 0) <li>• {{ $fixSummary['genre_mismatch'] }} genres</li> @endif
                            @if($fixSummary['duplicate'] > 0) <li>• {{ $fixSummary['duplicate'] }} doublons</li> @endif
                            @if($fixSummary['age_mismatch'] > 0) <li>• {{ $fixSummary['age_mismatch'] }} catégories</li> @endif
                        </ul>
                    </div>
                    <button wire:click="bulkFix" 
                            wire:confirm="Voulez-vous appliquer ces {{ array_sum($fixSummary) }} corrections automatiques sur les résultats affichés ?"
                            class="btn btn-xs btn-warning">
                        Tout corriger ({{ array_sum($fixSummary) }})
                    </button>
                </div>
                @endif
            </div>
        </div>
        @elseif($isFix && $errorCount === 0)
        <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-4 rounded shadow-sm animate-in slide-in-from-top duration-500">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-green-700">
                        Aucune anomalie détectée.
                    </p>
                </div>
            </div>
        </div>
        @endif

        @if (session()->has('bulk_success'))
        <div class="alert alert-success py-2 mb-4 text-xs">
            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-4 w-4" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <span>{{ session('bulk_success') }}</span>
        </div>
        @endif

        <div wire:loading class="absolute inset-0 bg-white/50 backdrop-blur-[1px] z-10 flex items-center justify-center rounded-lg">
            <span class="loading loading-spinner loading-md text-primary"></span>
        </div>

        <table class="table table-xs table-zebra w-full" wire:loading.class="opacity-50">
          <thead>
            <tr>
              <th></th>
              <th>Athlète</th>
              <th>Performance</th>
              <th>Année</th>
              <th>Compétition</th>
              <th>Lieu</th>
              <th>Rang</th>
              @if ($isFix)
              <th>Date</th>
              <th>ID</th>
              <th>ID: athlète</th>
              <th>Genre</th>
              <th>Contrôle</th>
              @if($canFix)
              <th>Actions</th>
              @endif
              @endif
            </tr>
          </thead>
          <tbody>
            @foreach ($results as $result)
            <tr wire:key="{{ $result->id }}" @if($isFix && !empty($result->diagnostics)) class="bg-orange-50/50 hover:bg-orange-100/50" @endif>
                <th>{{ $loop->iteration }}</th>
                <td>
                    <div class="flex flex-col">
                        <a href="{{ route('athletes.show', $result->athlete->id) }}" class="link link-hover text-slate-700 font-semibold whitespace-nowrap">
                            {{ $result->athlete->first_name }} {{ $result->athlete->last_name }}
                        </a>
                        @if ($isFix)
                        <span class="text-[10px] opacity-70">
                            @if($result->athlete->birthdate->year > 1900)
                                {{ $result->athlete->birthdate->format('Y') }} ({{ $result->event->date->year - $result->athlete->birthdate->year }} ans)
                            @else
                                <span class="text-orange-600 font-semibold italic">Année inconnue</span>
                            @endif
                        </span>
                        @endif
                    </div>
                </td>
                <td @if($isFix && collect($result->diagnostics)->contains('type', 'format_issue')) class="text-orange-700 font-bold" @endif>
                    {{ $result->performance }}
                </td>
                <td>{{ $result->event->date->format('Y') }}</td>
                <td class="max-w-xs truncate">{{ $result->event->name }}</td>
                <td>{{ $result->event->location }}</td>
                <td>{{ $result->rank }}</td>
                @if ($isFix)
                <td class="whitespace-nowrap">{{ $result->event->date->format('d.m.Y') }}</td>
                <td class="font-mono text-[10px] opacity-70">{{ $result->id }}</td>
                <td class="font-mono text-[10px] opacity-70">{{ $result->athlete->id }}</td>
                <td>{{ $result->athlete->genre }}</td>
                <td>
                    <div class="flex flex-col gap-1">
                        @foreach ($result->diagnostics as $diagnostic)
                            <div class="badge badge-error badge-outline badge-xs whitespace-nowrap gap-1 py-2">
                                @if($diagnostic['severity'] === 'warning')
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-3 h-3 stroke-current"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                @else
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-3 h-3 stroke-current"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                @endif
                                {{ $diagnostic['label'] }}
                            </div>
                            @if($showSql && isset($diagnostic['sql_fix']))
                            <div class="text-[9px] font-mono bg-slate-100 p-1 rounded border overflow-x-auto max-w-[150px] mt-1">
                                {{ $diagnostic['sql_fix'] }}
                            </div>
                            @endif
                        @endforeach
                    </div>
                </td>
                @if($canFix)
                <td>
                    <div class="flex flex-col gap-1">
                        @foreach ($result->diagnostics as $diagnostic)
                            @if($diagnostic['type'] === 'genre_mismatch')
                                <button wire:click="syncAthleteGenre({{ $result->athlete->id }}, '{{ $result->athleteCategory->genre }}')" 
                                        class="btn btn-xs btn-outline btn-warning py-0 h-auto min-h-0 text-[9px] lowercase"
                                        wire:loading.attr="disabled">
                                    Fix Genre
                                </button>
                            @elseif($diagnostic['type'] === 'duplicate')
                                <button wire:click="deleteResult({{ $result->id }})" 
                                        class="btn btn-xs btn-outline btn-error py-0 h-auto min-h-0 text-[9px] lowercase"
                                        wire:confirm="Supprimer ce résultat ?"
                                        wire:loading.attr="disabled">
                                    Delete
                                </button>
                            @elseif($diagnostic['type'] === 'age_mismatch' && isset($diagnostic['suggested_category_id']))
                                <button wire:click="changeCategory({{ $result->id }}, {{ $diagnostic['suggested_category_id'] }})" 
                                        class="btn btn-xs btn-outline btn-neutral py-0 h-auto min-h-0 text-[10px] lowercase"
                                        wire:loading.attr="disabled">
                                    Fix Catégorie
                                </button>
                            @elseif($diagnostic['type'] === 'format_issue')
                                <button onclick="let p = prompt('Nouvelle performance:', '{{ $result->performance }}'); if(p) @this.call('updatePerformance', {{ $result->id }}, p)" 
                                        class="btn btn-xs btn-outline btn-info py-0 h-auto min-h-0 text-[10px] lowercase"
                                        wire:loading.attr="disabled">
                                    Fix Perf
                                </button>
                            @endif
                        @endforeach
                        @if(empty($result->diagnostics))
                            <span class="text-green-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </span>
                        @endif
                    </div>
                </td>
                @endif
                @endif
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
</div>
