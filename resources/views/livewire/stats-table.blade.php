<div class="space-y-4 animate-in fade-in duration-700">
    {{-- Search and Filters Header --}}
    <div class="relative z-[100] bg-white rounded-2xl shadow-sm border border-slate-200/60 p-4">
        <div class="flex flex-col lg:flex-row lg:items-end gap-4">
            {{-- Discipline Selector --}}
            <div class="flex-1 space-y-1">
                <label class="text-[9px] font-bold uppercase tracking-wider text-slate-400 ml-1">Discipline</label>
                <div x-data="{
                    open: false,
                    search: '',
                    disciplines: @js($disciplines),
                    get filteredDisciplines() {
                        if (this.search === '') return this.disciplines;
                        const s = this.search.toLowerCase();
                        return this.disciplines.filter(d => (d.name_fr || '').toLowerCase().includes(s));
                    },
                    select(id) {
                        $wire.set('disciplineId', id);
                        this.open = false;
                        this.search = '';
                    }
                }" 
                class="relative" 
                @click.outside="open = false"
                @keydown.escape.window="open = false">
                    <button type="button" 
                            class="w-full flex items-center justify-between bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-700 hover:border-primary/50 transition-all duration-200 shadow-sm" 
                            @click="open = !open">
                        <span class="font-medium truncate text-sm">{{ $disciplines->firstWhere('id', $disciplineId)?->name_fr ?? 'Choisir une discipline' }}</span>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 text-slate-400 transition-transform duration-300" :class="open && 'rotate-180'">
                            <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                        </svg>
                    </button>

                    <div class="absolute z-[110] w-full mt-1 bg-white rounded-xl shadow-2xl border border-slate-200 p-1.5 transform origin-top transition-all duration-200" 
                         x-show="open" 
                         x-cloak
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 -translate-y-2 scale-95"
                         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                         x-transition:leave-end="opacity-0 -translate-y-2 scale-95">
                        <div class="px-1.5 pb-1.5 border-b border-slate-100 mb-1.5">
                            <input type="text" 
                                   x-model="search" 
                                   x-ref="searchInput"
                                   placeholder="Rechercher..." 
                                   class="w-full bg-slate-50 border border-slate-100 rounded-lg px-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" 
                                   @click.stop 
                                   @keydown.enter.prevent="if(filteredDisciplines.length > 0) select(filteredDisciplines[0].id)"
                                   x-effect="if(open) $nextTick(() => $refs.searchInput.focus())">
                        </div>
                        <ul class="max-h-52 overflow-y-auto custom-scrollbar space-y-0.5">
                            <template x-for="discipline in filteredDisciplines" :key="discipline.id">
                                <li>
                                    <button type="button" 
                                            @click="select(discipline.id)" 
                                            :class="discipline.id == @js($disciplineId) ? 'bg-primary text-white shadow-md shadow-primary/20' : 'hover:bg-slate-50 text-slate-600'" 
                                            class="w-full text-left px-3 py-1.5 rounded-lg transition-all duration-200 text-xs font-medium">
                                        <span x-text="discipline.name_fr"></span>
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- Category Selector --}}
            <div class="flex-1 space-y-1">
                <div class="flex items-center justify-between px-1">
                    <label class="text-[9px] font-bold uppercase tracking-wider text-slate-400">Catégorie</label>
                    @if($categoryId && $athleteCategories->firstWhere('id', $categoryId)?->age_limit < 99)
                    <div class="flex items-center gap-1.5 px-1 animate-in fade-in slide-in-from-right-2 duration-500">
                        <input type="checkbox" class="checkbox checkbox-xs checkbox-primary rounded-md" id="inclusiveCategory" wire:model.live="inclusiveCategory" />
                        <label for="inclusiveCategory" class="text-[9px] font-medium text-slate-500 cursor-pointer hover:text-primary transition-colors">Plus jeunes</label>
                    </div>
                    @endif
                </div>
                <select class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-700 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all appearance-none cursor-pointer shadow-sm" wire:model.live="categoryId">
                    <option value="">Toutes les catégories</option>
                    @foreach ($athleteCategories as $athleteCategory)
                    <option value="{{ $athleteCategory->id }}">{{ $athleteCategory->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Genre Selector --}}
            <div class="lg:w-40 space-y-1">
                <label class="text-[9px] font-bold uppercase tracking-wider text-slate-400 ml-1">Genre</label>
                <div class="flex bg-slate-100 p-0.5 rounded-xl shadow-inner border border-slate-200/50">
                    <button wire:click="$set('genre', '')" class="flex-1 py-1.5 text-[10px] font-bold rounded-lg transition-all duration-200 {{ $genre === null || $genre === '' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-400 hover:text-slate-600' }}">Tous</button>
                    <button wire:click="$set('genre', 'm')" class="flex-1 py-1.5 text-[10px] font-bold rounded-lg transition-all duration-200 {{ $genre === 'm' ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-400 hover:text-slate-600' }}">H</button>
                    <button wire:click="$set('genre', 'w')" class="flex-1 py-1.5 text-[10px] font-bold rounded-lg transition-all duration-200 {{ $genre === 'w' ? 'bg-white text-pink-600 shadow-sm' : 'text-slate-400 hover:text-slate-600' }}">F</button>
                </div>
            </div>
            
            {{-- Fix Mode Toggle (Local Only) --}}
            @if(app()->isLocal())
            <div class="flex lg:pb-0.5">
                <button wire:click="$toggle('fix')" class="group flex items-center gap-2 px-3 py-2 rounded-xl border transition-all duration-300 {{ $fix ? 'bg-amber-50 border-amber-200 text-amber-700 shadow-sm shadow-amber-100' : 'bg-slate-50 border-slate-200 text-slate-400 hover:border-slate-300 hover:text-slate-500' }}">
                    <div class="w-1.5 h-1.5 rounded-full {{ $fix ? 'bg-amber-500 animate-pulse' : 'bg-slate-300 group-hover:bg-slate-400' }}"></div>
                    <span class="text-[10px] font-bold uppercase tracking-tight">Diag</span>
                </button>
            </div>
            @endif
        </div>

        @if ($fix)
        <div class="mt-4 pt-4 border-t border-slate-100 flex flex-wrap items-center gap-4 animate-in slide-in-from-top-2 duration-500">
            <div class="flex items-center gap-2">
                <input type="checkbox" class="toggle toggle-info toggle-xs" id="showOnlyErrors" wire:model.live="showOnlyErrors" />
                <label for="showOnlyErrors" class="text-[10px] font-semibold text-slate-600 cursor-pointer">Erreurs uniquement</label>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" class="toggle toggle-secondary toggle-xs" id="showSql" wire:click="toggleSql" />
                <label for="showSql" class="text-[10px] font-semibold text-slate-600 cursor-pointer">Voir SQL (Full)</label>
            </div>
        </div>
        @endif
    </div>

    {{-- Results Section --}}
    <div class="relative z-0">
        {{-- Status Bar / Top Indicator --}}
        <div class="flex items-center justify-between mb-2 px-1">
            <div class="flex items-center gap-2">
                <h2 class="text-sm font-bold text-slate-800">Meilleures performances</h2>
                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-black bg-primary/10 text-primary border border-primary/20"> {{ $fix ? 'DEBUG ('.count($results).')' : 'TOP 100' }}</span>
            </div>
            <div class="text-[10px] font-medium text-slate-400">
                {{ count($results) }} résultat{{ count($results) > 1 ? 's' : '' }}
            </div>
        </div>

        {{-- Diagnostic Alerts --}}
        @if ($isFix && $errorCount > 0)
        <div class="bg-amber-50 border border-amber-200 p-3 mb-4 rounded-xl shadow-sm animate-in slide-in-from-top-4 duration-700">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <div class="p-1.5 bg-amber-100 rounded-lg text-amber-600">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-amber-900">{{ $errorCount }} anomalie{{ $errorCount > 1 ? 's' : '' }} détectée{{ $errorCount > 1 ? 's' : '' }}</p>
                    </div>
                </div>

                @if($canFix && array_sum($fixSummary) > 0)
                <div class="flex items-center gap-3">
                    <div class="flex flex-wrap gap-x-2 text-[9px] font-bold text-amber-800/60 uppercase tracking-tighter">
                        @if($fixSummary['genre_mismatch'] > 0) <span>• {{ $fixSummary['genre_mismatch'] }} genres</span> @endif
                        @if($fixSummary['duplicate'] > 0) <span>• {{ $fixSummary['duplicate'] }} doublons</span> @endif
                        @if($fixSummary['age_mismatch'] > 0) <span>• {{ $fixSummary['age_mismatch'] }} catégories</span> @endif
                        @if($fixSummary['missing_relation'] > 0) <span>• {{ $fixSummary['missing_relation'] }} orphelins</span> @endif
                    </div>
                    <button wire:click="bulkFix" 
                            wire:confirm="Appliquer {{ array_sum($fixSummary) }} corrections ?"
                            class="px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-white text-[10px] font-bold rounded-lg transition-all shadow-md active:scale-95 whitespace-nowrap">
                        Tout corriger ({{ array_sum($fixSummary) }})
                    </button>
                </div>
                @endif
            </div>
        </div>
        @endif

        @if (session()->has('bulk_success'))
        <div class="bg-emerald-500 text-white px-4 py-2 rounded-xl mb-4 flex items-center gap-2 shadow-lg shadow-emerald-500/20 animate-in slide-in-from-right-4 duration-500">
            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-white shrink-0 h-4 w-4" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <span class="text-xs font-bold">{{ session('bulk_success') }}</span>
        </div>
        @endif

        {{-- Main Table Container --}}
        <div class="bg-white rounded-2xl shadow-xl shadow-slate-200/40 border border-slate-100 overflow-hidden relative">
            {{-- Loading State --}}
            <div wire:loading class="absolute inset-0 bg-white/60 backdrop-blur-[1px] z-50 flex items-center justify-center transition-all duration-300">
                <span class="loading loading-ring loading-md text-primary"></span>
            </div>

            <div class="overflow-x-auto">
                <table class="table w-full border-separate border-spacing-0" wire:loading.class="opacity-50">
                    <thead>
                        <tr class="bg-slate-50/80">
                            <th class="py-3 pl-4 w-10 text-[9px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100">#</th>
                            <th class="py-3 text-[9px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100">Athlète</th>
                            <th class="py-3 text-[9px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 text-center">Perf</th>
                            <th class="py-3 text-[9px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 text-center">Âge</th>
                            <th class="py-3 text-[9px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 text-center">An</th>
                            <th class="py-3 text-[9px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100">Compétition / Lieu</th>
                            <th class="py-3 pr-4 w-16 text-[9px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 text-right">Rang</th>
                            @if ($isFix)
                            <th class="py-3 text-[9px] font-bold uppercase tracking-wider text-amber-500 border-b border-slate-100">Audit / Fixes</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @forelse ($results as $result)
                        <tr wire:key="{{ $result->id }}" class="group hover:bg-slate-50/60 transition-all duration-150 {{ $isFix && !empty($result->diagnostics) ? 'bg-amber-50/20' : '' }}">
                            <td class="pl-4 py-2.5">
                                <span class="text-[10px] font-bold text-slate-300 group-hover:text-primary transition-colors">{{ $loop->iteration }}</span>
                            </td>
                            <td class="py-2.5">
                                <div class="flex flex-col">
                                    <a href="{{ $result->athlete ? route('athletes.show', $result->athlete->id) : '#' }}" 
                                       class="text-xs font-bold text-slate-700 hover:text-primary transition-colors whitespace-nowrap {{ !$result->athlete ? 'text-rose-500' : '' }}">
                                        {{ $result->athlete ? $result->athlete->first_name . ' ' . $result->athlete->last_name : 'Athlète Inconnu' }}
                                    </a>
                                    @if ($isFix)
                                    <span class="text-[8px] font-mono text-slate-400 leading-none mt-1">ID {{ $result->id }} / A{{ $result->athlete_id }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="py-2.5 text-center">
                                <span class="inline-flex px-2 py-0.5 rounded-lg text-xs font-black tabular-nums {{ $isFix && collect($result->diagnostics)->contains('type', 'format_issue') ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-800' }}">
                                    {{ $result->performance }}
                                </span>
                            </td>
                            <td class="py-2.5 text-center">
                                <span class="text-[10px] font-medium text-slate-500 tabular-nums">
                                    {{ $result->athlete_age ?? '—' }}
                                </span>
                            </td>
                            <td class="py-2.5 text-center">
                                <span class="text-[10px] font-black text-slate-400 tabular-nums">
                                    {{ $result->event?->date?->format('Y') ?? '?' }}
                                </span>
                            </td>
                            <td class="py-2.5">
                                <div class="flex flex-col max-w-xs xl:max-w-sm">
                                    <span class="text-[10px] text-slate-600 font-medium truncate">{{ $result->event?->name ?? '---' }}</span>
                                    <span class="text-[8px] text-slate-400 flex items-center gap-0.5">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-2 h-2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                        </svg>
                                        {{ $result->event?->location ?? 'N/A' }}
                                    </span>
                                </div>
                            </td>
                            <td class="py-2.5 pr-4 text-right">
                                <span class="text-xs font-black tabular-nums {{ $result->rank ? 'text-slate-600' : 'text-slate-300' }}">
                                    {{ $result->rank ?? '—' }}
                                </span>
                            </td>

                            @if ($isFix)
                            <td class="py-2.5">
                                <div class="space-y-1.5 min-w-[200px]">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($result->diagnostics as $diagnostic)
                                            <div class="tooltip tooltip-bottom" data-tip="{{ $diagnostic['label'] }}">
                                                <div class="px-1.5 py-0.5 rounded bg-rose-50 text-rose-600 border border-rose-100 text-[8px] font-black uppercase cursor-help">
                                                    {{ $diagnostic['type'][0] }}
                                                </div>
                                            </div>
                                        @endforeach
                                        
                                        <div class="flex gap-1">
                                            @foreach ($result->diagnostics as $diagnostic)
                                                @if($diagnostic['type'] === 'genre_mismatch')
                                                    <button wire:click="syncAthleteGenre({{ $result->athlete->id }}, '{{ $result->athleteCategory->genre }}')" 
                                                            class="px-1.5 py-0.5 bg-amber-500 text-white text-[8px] font-bold rounded hover:bg-amber-600" wire:loading.attr="disabled">GNR</button>
                                                @elseif($diagnostic['type'] === 'duplicate')
                                                    <button wire:click="deleteResult({{ $result->id }})" 
                                                            class="px-1.5 py-0.5 bg-rose-500 text-white text-[8px] font-bold rounded hover:bg-rose-600" 
                                                            wire:confirm="Supprimer ce DOUBLON spécifique (ID: {{ $result->id }}) ?" 
                                                            wire:loading.attr="disabled">DEL</button>
                                                @elseif($diagnostic['type'] === 'age_mismatch' && isset($diagnostic['suggested_category_id']))
                                                    <button wire:click="changeCategory({{ $result->id }}, {{ $diagnostic['suggested_category_id'] }})" 
                                                            class="px-1.5 py-0.5 bg-slate-700 text-white text-[8px] font-bold rounded hover:bg-slate-800" wire:loading.attr="disabled">CAT</button>
                                                @elseif($diagnostic['type'] === 'format_issue')
                                                    <button onclick="let p = prompt('Format:', '{{ $result->performance }}'); if(p) @this.call('updatePerformance', {{ $result->id }}, p)" 
                                                            class="px-1.5 py-0.5 bg-blue-500 text-white text-[8px] font-bold rounded hover:bg-blue-600" wire:loading.attr="disabled">PERF</button>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                    
                                    @if($showSql && !empty($result->diagnostics))
                                        @foreach ($result->diagnostics as $diagnostic)
                                            @if(isset($diagnostic['sql_fix']))
                                            <div class="relative group/sql">
                                                <div class="bg-slate-900 text-slate-300 font-mono text-[8px] p-1 px-1.5 rounded border border-slate-700 overflow-x-auto whitespace-pre select-all leading-relaxed">{{ trim($diagnostic['sql_fix']) }}</div>
                                                <div class="absolute right-1 top-1 opacity-0 group-hover/sql:opacity-100 transition-opacity bg-slate-800 text-white text-[7px] px-1 rounded border border-slate-600 pointer-events-none uppercase">SQL</div>
                                            </div>
                                            @endif
                                        @endforeach
                                    @endif
                                </div>
                            </td>
                            @endif
                        </tr>
                        @empty
                        <tr>
                            <td colspan="100" class="py-12 text-center">
                                <p class="text-[10px] text-slate-400 font-medium">Aucun résultat trouvé.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
