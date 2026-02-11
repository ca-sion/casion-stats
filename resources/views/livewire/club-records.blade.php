<div class="mt-6" wire:init="loadData">
    {{-- Glassmorphism Header --}}
    <div class="bg-white/70 backdrop-blur-xl border-b border-slate-200/50 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 py-8">
            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6">
                <div>
                    <div class="inline-flex items-center gap-2 mb-2">
                        <div class="w-1 h-8 bg-gradient-to-b from-slate-700 to-slate-900 rounded-full"></div>
                        <h1 class="text-3xl font-black text-slate-900 tracking-tight">
                            Records du Club
                        </h1>
                    </div>
                    <p class="text-slate-600 text-sm font-medium ml-3">
                        Top 10 des meilleures performances par discipline officielle World Athletics
                    </p>
                </div>

                {{-- Gender Filter with Glassmorphism --}}
                <div class="flex gap-2 bg-white/50 backdrop-blur-md p-1.5 rounded-xl border border-slate-200/50 shadow-sm">
                    <button wire:click="setGenre('')" 
                            class="px-5 py-2.5 text-sm font-bold rounded-lg transition-all duration-200 {{ $genre === '' ? 'bg-slate-900 text-white shadow-lg shadow-slate-900/20' : 'text-slate-600 hover:bg-white/80' }}">
                        Tous
                    </button>
                    <button wire:click="setGenre('m')" 
                            class="px-5 py-2.5 text-sm font-bold rounded-lg transition-all duration-200 {{ $genre === 'm' ? 'bg-slate-900 text-white shadow-lg shadow-slate-900/20' : 'text-slate-600 hover:bg-white/80' }}">
                        Hommes
                    </button>
                    <button wire:click="setGenre('w')" 
                            class="px-5 py-2.5 text-sm font-bold rounded-lg transition-all duration-200 {{ $genre === 'w' ? 'bg-slate-900 text-white shadow-lg shadow-slate-900/20' : 'text-slate-600 hover:bg-white/80' }}">
                        Femmes
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Enhanced Loading State --}}
    <div wire:loading class="w-full flex items-center justify-center mt-12">
        <div class="text-center">
            <div class="relative inline-flex mb-6">
                <div class="w-16 h-16 border-4 border-slate-200 border-t-slate-900 rounded-full animate-spin"></div>
                <div class="absolute inset-0 flex items-center justify-center">
                    <div class="text-2xl">🏃</div>
                </div>
            </div>
            <p class="text-slate-700 font-bold text-lg">Chargement des records</p>
            <p class="text-slate-500 text-sm mt-1">Analyse des performances en cours...</p>
        </div>
    </div>

    {{-- Records Grid with Glassmorphism --}}
    @if($readyToLoad && $disciplines->isNotEmpty())
    <div wire:loading.remove class="max-w-7xl mx-auto px-4 py-8">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach($disciplines as $discipline)
                @php
                    $records = $recordsByDiscipline->get($discipline->id, collect());
                    $currentYear = 2026;
                    $previousYear = 2025;
                @endphp
                
                @if($records->isNotEmpty())
                <div class="group bg-white/60 backdrop-blur-md rounded-2xl border border-slate-200/60 shadow-sm hover:shadow-lg hover:bg-white/80 transition-all duration-300">
                    {{-- Discipline Header with Gradient --}}
                    <div class="relative overflow-hidden border-b border-slate-200/60 px-5 py-4 bg-gradient-to-br from-slate-50/80 to-white/40 backdrop-blur-sm">
                        <div class="absolute inset-0 bg-gradient-to-r from-slate-900/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                        <h3 class="relative font-black text-slate-900 text-base tracking-tight">
                            {{ $discipline->name_fr }}
                        </h3>
                        <a href="/?discipline={{ $discipline->id }}{{ $genre ? '&genre=' . $genre : '' }}" 
                           class="relative inline-flex items-center gap-1 text-slate-500 hover:text-slate-900 text-xs font-semibold mt-1.5 transition-colors group/link">
                            <span>Voir tous les résultats</span>
                            <svg class="w-3 h-3 transition-transform group-hover/link:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    </div>

                    {{-- Top 10 Athletes --}}
                    <div class="divide-y divide-slate-100/50">
                        @foreach($records as $index => $result)
                        @php
                            $year = $result->event?->date?->format('Y');
                            $isRecent = $year == $currentYear || $year == $previousYear;
                        @endphp
                        <div class="px-5 py-3 hover:bg-slate-50/50 transition-all duration-200 {{ $isRecent ? 'bg-amber-50/40 hover:bg-amber-50/60' : '' }}">
                            <div class="flex items-center gap-3">
                                {{-- Rank Badge --}}
                                <div class="flex-shrink-0 w-7 h-7 rounded-lg bg-gradient-to-br from-slate-100 to-slate-200 flex items-center justify-center border border-slate-300/50 shadow-sm">
                                    <span class="text-slate-700 text-xs font-black">{{ $index + 1 }}</span>
                                </div>

                                {{-- Athlete Name --}}
                                <div class="flex-1 min-w-0">
                                    <a href="{{ route('athletes.show', $result->athlete_id) }}" 
                                       class="block hover:text-slate-900 transition-colors group/athlete">
                                        <div class="font-bold text-sm text-slate-700 truncate group-hover/athlete:text-slate-900">
                                            {{ $result->athlete->first_name }} {{ $result->athlete->last_name }}
                                        </div>
                                    </a>
                                </div>

                                {{-- Performance & Year --}}
                                <div class="flex-shrink-0 text-right">
                                    <div class="font-black text-lg text-slate-900 tabular-nums leading-tight {{ $isRecent ? 'text-amber-700' : '' }}">
                                        {{ $result->performance }}
                                    </div>
                                    <div class="text-xs font-bold tabular-nums {{ $isRecent ? 'text-amber-600' : 'text-slate-400' }}">
                                        {{ $year ?? '?' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            @endforeach
        </div>

        @if($recordsByDiscipline->isEmpty())
        <div class="text-center py-32">
            <div class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-slate-100/50 backdrop-blur-sm border border-slate-200/50 mb-6">
                <div class="text-5xl opacity-30">🏆</div>
            </div>
            <p class="text-xl font-bold text-slate-400">Aucun record trouvé</p>
            <p class="text-sm text-slate-400 mt-2">Essayez de modifier les filtres</p>
        </div>
        @endif
    </div>
    @endif
</div>
