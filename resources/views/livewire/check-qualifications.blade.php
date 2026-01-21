<div class="container mx-auto p-4 max-w-6xl">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Vérification des Qualifications</h1>
    </div>

    {{-- Configuration Card --}}
    <div class="card bg-base-100 shadow-xl mb-8">
        <div class="card-body">
            <h2 class="card-title mb-4">Configuration</h2>
            
            <form wire:submit.prevent="check">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    {{-- Limites --}}
                    <div>
                        <label class="label">
                            <span class="label-text font-semibold">Fichier des Limites (JSON)</span>
                        </label>
                        <input type="file" wire:model="limitsFile" class="file-input file-input-bordered w-full" accept=".json,.txt" />
                        @error('limitsFile') <span class="text-error text-sm">{{ $message }}</span> @enderror
                        <div class="label">
                            <span class="label-text-alt text-gray-500">Le fichier contenant les années et les limites par discipline.</span>
                        </div>
                    </div>

                    {{-- Source Résultats --}}
                    <div>
                        <label class="label">
                            <span class="label-text font-semibold">Source des Résultats Récents (Optionnel)</span>
                        </label>
                        
                        <div class="join mb-2">
                            <input class="join-item btn" type="radio" wire:model.live="sourceType" value="files" aria-label="Fichiers Locaux" />
                            <input class="join-item btn" type="radio" wire:model.live="sourceType" value="urls" aria-label="URLs Distantes" />
                        </div>

                        @if($sourceType === 'files')
                            <input type="file" wire:model="resultFiles" class="file-input file-input-bordered w-full" multiple accept=".html,.htm" />
                            <div class="label">
                                <span class="label-text-alt text-gray-500">Sélectionnez un ou plusieurs fichiers HTML.</span>
                            </div>
                        @else
                            <textarea wire:model="resultUrls" class="textarea textarea-bordered w-full h-24" placeholder="https://.../resultats.html"></textarea>
                            <div class="label">
                                <span class="label-text-alt text-gray-500">Une URL par ligne.</span>
                            </div>
                        @endif
                        @error('resultFiles.*') <span class="text-error text-sm">{{ $message }}</span> @enderror
                        @error('resultUrls') <span class="text-error text-sm">{{ $message }}</span> @enderror
                    </div>
                </div>

                @if($errorMsg)
                    <div role="alert" class="alert alert-error mt-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span>{{ $errorMsg }}</span>
                    </div>
                @endif

                <div class="card-actions justify-end mt-6">
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                        <span wire:loading class="loading loading-spinner"></span>
                        Vérifier les Qualifications
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Results --}}
    @if($results !== null)
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <div class="flex flex-col md:flex-row justify-between items-center mb-4 gap-4">
                    <h2 class="card-title">Résultats Qualifiés</h2>
                    <div class="flex gap-2">
                         <div class="badge badge-lg">
                            {{ $stats['analyzed'] }} Analysés (sur {{ $stats['raw_fetched'] }} trouvés)
                        </div>
                        <div class="badge badge-lg badge-success text-white">
                            {{ $stats['qualified'] }} Qualifiés
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    @php
                        $groupedResults = collect($results)->groupBy('athlete_name')->sortBy('athlete_name');
                    @endphp

                    @forelse($groupedResults as $athleteName => $athleteResults)
                        <div class="bg-base-200 p-4 rounded-lg mb-4">
                            <h3 class="font-bold text-lg mb-2 flex items-center gap-2">
                                {{ $athleteName }}
                                <span class="badge badge-outline">{{ $athleteResults->first()['birth_year'] }}</span>
                            </h3>
                            
                            <table class="table table-sm bg-base-100 rounded-lg">
                                <thead>
                                    <tr>
                                        <th>Discipline</th>
                                        <th>Année (Perf)</th>
                                        <th>Catégorie (Calc)</th>
                                        <th>Performance</th>
                                        <th>Limite</th>
                                        <th>Source</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($athleteResults as $res)
                                        <tr>
                                            <td class="font-medium">
                                                <div class="tooltip" data-tip="{{ $res['discipline_raw'] ?? $res['discipline_name'] }}">
                                                    {{ 
                                                        $res['discipline_name'] 
                                                        ?? ($res['discipline_matched']['discipline'] ?? ($res['discipline_matched']['name'] ?? ($res['discipline_raw'] ?? 'Inconnue'))) 
                                                    }}
                                                </div>
                                            </td>
                                            <td>{{ $res['year'] }}</td>
                                            <td>
                                                <div class="badge badge-ghost badge-sm">{{ $res['category_hit'] ?? '-' }}</div>
                                            </td>
                                            <td class="font-mono font-bold text-success">
                                                {{ $res['performance_formatted'] }}
                                            </td>
                                            <td class="font-mono text-gray-500 text-xs">
                                                {{ $res['limit_hit'] ?? '-' }}
                                            </td>
                                            <td>
                                                @if($res['source'] === 'DB')
                                                    <span class="badge badge-primary badge-outline badge-xs">DB</span>
                                                @else
                                                    <span class="badge badge-secondary badge-outline badge-xs">HTML</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-500">
                            Aucun athlète qualifié trouvé avec ces limites.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>
