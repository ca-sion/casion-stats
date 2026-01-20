<div class="relative min-h-screen bg-slate-50/50 p-6 md:p-10">
    <!-- Header Section -->
    <div class="max-w-7xl mx-auto mb-8">
        <h2 class="text-4xl font-extrabold text-slate-900 tracking-tight mb-2">Gestion des Disciplines</h2>
        <p class="text-slate-500 text-lg">Gérez, fusionnez et nettoyez vos disciplines avec une vue détaillée des données.</p>
    </div>

    <!-- Sticky Toolbar -->
    <div class="sticky top-4 z-20 max-w-7xl mx-auto mb-8">
        <div class="bg-white/80 backdrop-blur-md border border-slate-200 shadow-lg rounded-2xl p-4 flex flex-col md:flex-row gap-4 items-center justify-between">
            <div class="relative w-full md:w-96">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                </span>
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Rechercher par nom, code, WA..." class="input input-bordered w-full pl-10 bg-white border-slate-200 focus:ring-2 focus:ring-primary/20" />
            </div>

            <div class="flex items-center gap-3">
                @if(count($selectedIds) > 0)
                    <div class="animate-in fade-in slide-in-from-right-4 duration-300 flex items-center gap-3 bg-primary/10 px-4 py-2 rounded-xl border border-primary/20">
                        <span class="text-primary font-bold text-sm">{{ count($selectedIds) }} sélectionné(s)</span>
                        <button wire:click="openMergeModal" class="btn btn-primary btn-sm rounded-lg shadow-sm">
                            Fusionner
                        </button>
                    </div>
                @endif
                <div wire:loading class="loading loading-spinner loading-md text-primary"></div>
            </div>
        </div>
    </div>

    <!-- Disciplines Grid/List -->
    <div class="max-w-7xl mx-auto space-y-4">
        @forelse($disciplines as $discipline)
            <div class="group relative bg-white border border-slate-200 rounded-2xl p-5 transition-all duration-300 hover:shadow-xl hover:border-primary/30 {{ in_array($discipline->id, $selectedIds) ? 'ring-2 ring-primary bg-primary/5 border-primary/40' : '' }}">
                <div class="flex flex-col lg:flex-row gap-6">
                    <!-- Selection & Basic Info -->
                    <div class="flex items-start gap-4 flex-1">
                        <label class="cursor-pointer pt-1">
                            <input type="checkbox" 
                                   wire:click="toggleSelection({{ $discipline->id }})" 
                                   {{ in_array($discipline->id, $selectedIds) ? 'checked' : '' }}
                                   class="checkbox checkbox-primary rounded-md border-slate-300" />
                        </label>
                        
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="bg-slate-100 text-slate-500 text-[10px] font-mono px-1.5 py-0.5 rounded border border-slate-200 uppercase">ID: {{ $discipline->id }}</span>
                                <span class="badge badge-sm badge-neutral font-mono">{{ $discipline->code ?: 'NO-CODE' }}</span>
                                @if($discipline->wa_code) <span class="badge badge-sm badge-outline font-mono">WA: {{ $discipline->wa_code }}</span> @endif
                            </div>
                            <h3 class="text-xl font-bold text-slate-900 group-hover:text-primary transition-colors">{{ $discipline->name_fr }}</h3>
                            <div class="text-slate-500 italic text-sm">{{ $discipline->name_de }}</div>
                            
                            <!-- Full Metadata Dashboard -->
                            <div class="mt-4 grid grid-cols-2 md:grid-cols-3 gap-y-3 gap-x-6">
                                <div class="col-span-1">
                                    <div class="text-[10px] uppercase font-bold text-slate-400 mb-0.5">Codes & IDs</div>
                                    <div class="space-y-1">
                                        <div class="text-xs flex justify-between"><span class="text-slate-500">Lanet / Seltec ID:</span> <span class="font-mono {{ $discipline->seltec_id ? 'text-slate-900 font-bold' : 'text-slate-300' }}">{{ $discipline->seltec_id ?: '-' }}</span></div>
                                        <div class="text-xs flex justify-between"><span class="text-slate-500">Alabus ID:</span> <span class="font-mono {{ $discipline->alabus_id ? 'text-slate-900 font-bold' : 'text-slate-300' }}">{{ $discipline->alabus_id ?: '-' }}</span></div>
                                        <div class="text-xs flex justify-between"><span class="text-slate-500">Code:</span> <span class="font-mono {{ $discipline->code ? 'text-slate-900 font-bold' : 'text-slate-300' }}">{{ $discipline->code ?: '-' }}</span></div>
                                    </div>
                                </div>
                                
                                <div class="col-span-1 border-l border-slate-100 pl-4">
                                    <div class="text-[10px] uppercase font-bold text-slate-400 mb-0.5">Fédérations</div>
                                    <div class="space-y-1">
                                        <div class="text-xs flex justify-between"><span class="text-slate-500">WA Code:</span> <span class="font-mono {{ $discipline->wa_code ? 'text-slate-900 font-bold' : 'text-slate-300' }}">{{ $discipline->wa_code ?: '-' }}</span></div>
                                        <div class="text-xs flex justify-between"><span class="text-slate-500">Seltec Code:</span> <span class="font-mono {{ $discipline->seltec_code ? 'text-slate-900 font-bold' : 'text-slate-300' }}">{{ $discipline->seltec_code ?: '-' }}</span></div>
                                        <div class="text-xs flex justify-between"><span class="text-slate-500">Type:</span> <span class="font-bold {{ $discipline->type ? 'text-slate-900' : 'text-slate-300' }}">{{ $discipline->type ?: '-' }}</span></div>
                                    </div>
                                </div>

                                <div class="col-span-1 border-l border-slate-100 pl-4">
                                    <div class="text-[10px] uppercase font-bold text-slate-400 mb-0.5">Propriétés</div>
                                    <div class="space-y-1">
                                        <div class="text-xs flex justify-between"><span class="text-slate-500">Vent:</span> <span class="font-bold {{ $discipline->has_wind ? 'text-emerald-600' : 'text-slate-300' }}">{{ $discipline->has_wind ? 'Oui' : 'Non' }}</span></div>
                                        <div class="text-xs flex justify-between"><span class="text-slate-500">Relais:</span> <span class="font-bold {{ $discipline->is_relay ? 'text-emerald-600' : 'text-slate-300' }}">{{ $discipline->is_relay ? 'Oui' : 'Non' }}</span></div>
                                        <div class="text-xs flex justify-between"><span class="text-slate-500">Tri:</span> <span class="font-mono text-slate-900">{{ $discipline->sorting ?: '-' }}</span></div>
                                    </div>
                                </div>

                                @if($discipline->name_en)
                                    <div class="col-span-full mt-1 pt-2 border-t border-slate-50 text-[11px] text-slate-400 italic">
                                        English: {{ $discipline->name_en }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Statistics & Samples -->
                    <div class="flex-1 border-t lg:border-t-0 lg:border-l border-slate-100 lg:pl-6 pt-4 lg:pt-0">
                        <div class="flex justify-between items-center mb-3">
                            <span class="text-sm font-bold text-slate-700">Utilisation</span>
                            <span class="badge {{ $discipline->results_count > 0 ? 'badge-primary' : 'badge-ghost text-slate-400' }} font-bold">
                                {{ $discipline->results_count }} résultats
                            </span>
                        </div>

                        <div class="space-y-2">
                            @forelse($discipline->samples as $sample)
                                <div class="flex items-center justify-between text-[11px] bg-slate-50 p-2 rounded-lg border border-slate-100">
                                    <div class="flex items-center gap-2 truncate flex-1">
                                        <span class="text-slate-900 font-medium truncate">{{ $sample['athlete_name'] }}</span>
                                        <span class="text-slate-400">•</span>
                                        <span class="text-slate-500">{{ $sample['date'] }}</span>
                                    </div>
                                    <div class="font-bold text-slate-900 ml-2">{{ $sample['performance'] }}</div>
                                </div>
                            @empty
                                <div class="flex flex-col items-center py-4 bg-slate-50 rounded-xl border border-dashed border-slate-200">
                                    <span class="text-slate-400 text-xs italic">Aucun résultat trouvé</span>
                                    @if($discipline->results_count === 0)
                                        <button 
                                            wire:click="deleteIfEmpty({{ $discipline->id }})"
                                            wire:confirm="Supprimer cette discipline inutile ?"
                                            class="mt-2 btn btn-xs btn-outline btn-error rounded-md"
                                        >
                                            Supprimer
                                        </button>
                                    @endif
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="py-20 text-center">
                <div class="text-slate-300 mb-4">
                    <svg class="h-16 w-16 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                </div>
                <h3 class="text-xl font-bold text-slate-900">Aucun résultat</h3>
                <p class="text-slate-500">Essayez une autre recherche.</p>
            </div>
        @endforelse

        <div class="mt-8">
            {{ $disciplines->links() }}
        </div>
    </div>

    <!-- Merge Modal -->
    <dialog class="modal {{ $isMergeModalOpen ? 'modal-open' : '' }}">
        <div class="modal-box max-w-2xl bg-white rounded-3xl p-8 border border-slate-200 shadow-2xl">
            <h3 class="text-3xl font-extrabold text-slate-900 mb-2">Fusionner les disciplines</h3>
            <p class="text-slate-500 mb-8">Choisissez la discipline que vous souhaitez conserver (la "Maître"). Les autres seront absorbées et supprimées.</p>

            <div class="space-y-3 mb-8">
                @foreach($selectedDisciplines as $item)
                    <label class="flex items-center gap-4 p-4 rounded-2xl border-2 transition-all cursor-pointer {{ $targetId == $item->id ? 'border-primary bg-primary/5' : 'border-slate-100 hover:border-slate-200' }}">
                        <input type="radio" wire:model.live="targetId" value="{{ $item->id }}" class="radio radio-primary" />
                        <div class="flex-1">
                            <div class="font-bold text-slate-900">{{ $item->name_fr }}</div>
                            <div class="text-xs text-slate-400">ID: {{ $item->id }} • {{ $item->code ?: '-' }} • {{ $item->results_count }} résultats</div>
                        </div>
                    </label>
                @endforeach
            </div>

            <div class="flex gap-3 justify-end">
                <button wire:click="closeMergeModal" class="btn btn-ghost rounded-xl">Annuler</button>
                <button wire:click="confirmMerge" class="btn btn-primary px-8 rounded-xl shadow-lg shadow-primary/20">
                    Confirmer la fusion
                </button>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop" wire:click="closeMergeModal">
            <button>close</button>
        </form>
    </dialog>

    <!-- Global Notification (Toast-style but via Alpine or simple alert) -->
    <div x-data="{ show: false, message: '', type: 'success' }"
         x-on:notify.window="show = true; message = $event.detail.message; type = $event.detail.type; setTimeout(() => show = false, 3000)"
         x-show="show"
         x-transition
         class="fixed bottom-10 left-1/2 -translate-x-1/2 z-[100]">
        <div :class="type === 'success' ? 'bg-emerald-500' : 'bg-rose-500'" class="text-white px-6 py-3 rounded-full shadow-2xl flex items-center gap-3">
            <template x-if="type === 'success'">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="5 13l4 4L19 7" /></svg>
            </template>
            <template x-if="type === 'error'">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </template>
            <span x-text="message" class="font-bold tracking-wide"></span>
        </div>
    </div>
</div>
