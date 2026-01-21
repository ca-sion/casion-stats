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
                            <div class="label flex flex-col items-start gap-1">
                                <span class="label-text-alt text-gray-500">Une URL par ligne.</span>
                                <span class="label-text-alt bg-info/10 p-2 rounded text-info border border-info/20">
                                    <strong>Exemple d'URL :</strong> https://slv.laportal.net/Competitions/Resultoverview/18123
                                </span>
                            </div>
                        @endif
                        @error('resultFiles.*') <span class="text-error text-sm">{{ $message }}</span> @enderror
                        @error('resultUrls') <span class="text-error text-sm">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="alert alert-info shadow-sm mt-4 bg-blue-50 border-blue-100 flex-col items-start gap-3">
                    <div class="flex gap-2 items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6 text-blue-600"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <strong class="text-blue-800">Fichiers d'exemples :</strong>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" wire:click="downloadExample('limits.example.json')" class="btn btn-xs btn-outline btn-info">limits.example.json ⬇️</button>
                        <button type="button" wire:click="downloadExample('classement.example.html')" class="btn btn-xs btn-outline btn-info">classement.example.html ⬇️</button>
                        <button type="button" wire:click="downloadExample('limits_global.example.json')" class="btn btn-xs btn-outline btn-info">limits_global.example.json ⬇️</button>
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
        {{-- Qualified Table --}}
        <div class="card bg-base-100 shadow-xl mb-8 border-t-4 border-success">
            <div class="card-body">
                <div class="flex flex-col md:flex-row justify-between items-center mb-4 gap-4">
                    <h2 class="card-title text-success">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        Athlètes Qualifiés
                    </h2>
                    <div class="flex gap-2 text-xs">
                         <div class="badge badge-outline">
                            {{ $stats['analyzed'] }} Analysés
                        </div>
                        <div class="badge badge-success text-white">
                            {{ $stats['qualified'] }} Confirmés
                        </div>
                    </div>
                </div>

                @php
                    $qualifiedResults = collect($results)->where('status', 'qualified');
                    $groupedQualified = $qualifiedResults->groupBy('athlete_name')->sortBy('athlete_name');
                @endphp

                <div class="overflow-x-auto">
                    @forelse($groupedQualified as $athleteName => $athleteResults)
                        <div class="bg-base-200/50 p-4 rounded-lg mb-4 border border-base-300">
                            <h3 class="font-bold text-lg mb-2 flex items-center gap-2">
                                {{ $athleteName }}
                                <span class="badge badge-sm badge-outline">{{ $athleteResults->first()['birth_year'] }}</span>
                            </h3>
                            
                            <table class="table table-sm bg-base-100 rounded-lg">
                                <thead>
                                    <tr class="bg-base-200/50">
                                        <th>Discipline</th>
                                        <th>Année</th>
                                        <th>Cat</th>
                                        <th>Perf</th>
                                        <th>Limite</th>
                                        <th>Source</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($athleteResults->groupBy('discipline_matched') as $disciplineCode => $paths)
                                        @php
                                            $directPath = $paths->firstWhere('via_secondary', null);
                                            $sample = $paths->first();
                                            // Primary data for the target discipline
                                            $primaryPerf = $sample['primary_performance_display'] ?? ($directPath['performance_display'] ?? '-');
                                            $primaryLimit = $sample['primary_limit'] ?? ($directPath['limit_hit'] ?? '-');
                                            $isQualifiedDirectly = $directPath && $directPath['status'] === 'qualified';
                                            $isNearMissDirectly = $directPath && $directPath['status'] === 'near_miss';
                                            $hasOnlySecondary = !$directPath;
                                        @endphp
                                        <tr class="hover border-t border-base-300">
                                            <td class="font-medium {{ ($sample['has_qualifies_for'] ?? false) ? 'text-slate-400' : 'text-primary' }} {{ $hasOnlySecondary ? 'pl-8' : '' }}">
                                                <div class="tooltip" data-tip="{{ $sample['discipline_raw'] }}">
                                                    {{ $sample['discipline_name'] }}
                                                    @if($hasOnlySecondary) <span class="text-[10px] opacity-70">(Via)</span> @endif
                                                </div>
                                            </td>
                                            <td class="text-xs opacity-70">{{ $sample['year'] }}</td>
                                            <td>
                                                <div class="badge badge-ghost badge-xs">{{ $sample['category_hit'] ?? '-' }}</div>
                                            </td>
                                            <td>
                                                <div class="font-mono font-bold whitespace-nowrap {{ $isQualifiedDirectly ? 'text-success' : ($isNearMissDirectly ? 'text-warning' : 'opacity-40') }}">
                                                    {{ $primaryPerf }}
                                                </div>
                                                @foreach($paths as $path)
                                                    @if(isset($path['via_secondary']))
                                                        <div class="text-[10px] text-slate-500 whitespace-nowrap font-normal mt-1">
                                                            Via {{ $path['via_secondary'] }}: {{ $path['secondary_perf'] }}
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </td>
                                            <td>
                                                <div class="font-mono text-xs opacity-40 whitespace-nowrap">
                                                    {{ $primaryLimit }}
                                                </div>
                                                @foreach($paths as $path)
                                                    @if(isset($path['via_secondary']))
                                                        <div class="text-[9px] text-slate-400 whitespace-nowrap font-normal mt-1 leading-tight">
                                                            (Lim: {{ $path['secondary_limit'] }} sur {{ $path['via_secondary'] }})
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </td>
                                            <td class="align-top pt-2">
                                                <span class="badge {{ $sample['source'] === 'DB' ? 'badge-info' : 'badge-warning' }} badge-outline text-[10px] h-4">
                                                    {{ $sample['source'] }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-400 italic">
                            Aucun athlète qualifié trouvé.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Near Misses Table --}}
        <div class="card bg-base-100 shadow-xl border-t-4 border-warning">
            <div class="card-body">
                <div class="flex flex-col md:flex-row justify-between items-center mb-4 gap-4">
                    <h2 class="card-title text-warning">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        Presque Qualifiés (Marge 5%)
                    </h2>
                    <div class="badge badge-warning text-white">
                        {{ $stats['near_miss'] }} Potentiels
                    </div>
                </div>

                @php
                    $nearMissResults = collect($results)->where('status', 'near_miss');
                    $groupedNearMiss = $nearMissResults->groupBy('athlete_name')->sortBy('athlete_name');
                @endphp

                <div class="overflow-x-auto">
                    @forelse($groupedNearMiss as $athleteName => $athleteResults)
                        <div class="bg-base-200/30 p-4 rounded-lg mb-4 border border-dashed border-warning/30">
                            <h3 class="font-bold text-lg mb-2 flex items-center gap-2">
                                {{ $athleteName }}
                                <span class="badge badge-sm badge-outline">{{ $athleteResults->first()['birth_year'] }}</span>
                            </h3>
                            
                            <table class="table table-sm bg-base-100 rounded-lg">
                                <thead>
                                    <tr class="bg-base-200/30">
                                        <th>Discipline</th>
                                        <th>Année</th>
                                        <th>Cat</th>
                                        <th>Perf</th>
                                        <th>Limite</th>
                                        <th>Source</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($athleteResults->groupBy('discipline_matched') as $disciplineCode => $paths)
                                        @php
                                            $directPath = $paths->firstWhere('via_secondary', null);
                                            $sample = $paths->first();
                                            $primaryPerf = $sample['primary_performance_display'] ?? ($directPath['performance_display'] ?? '-');
                                            $primaryLimit = $sample['primary_limit'] ?? ($directPath['limit_hit'] ?? '-');
                                            $hasOnlySecondary = !$directPath;
                                            $isNearMissDirectly = $directPath && $directPath['status'] === 'near_miss';
                                        @endphp
                                        <tr class="hover border-t border-dashed border-warning/30">
                                            <td class="font-medium {{ ($sample['has_qualifies_for'] ?? false) ? 'text-slate-400' : 'text-warning/80' }} {{ $hasOnlySecondary ? 'pl-8' : '' }}">
                                                <div class="tooltip" data-tip="{{ $sample['discipline_raw'] }}">
                                                    {{ $sample['discipline_name'] }}
                                                    @if($hasOnlySecondary) <span class="text-[10px] opacity-70">(Via)</span> @endif
                                                </div>
                                            </td>
                                            <td class="text-xs opacity-70">{{ $sample['year'] }}</td>
                                            <td>
                                                <div class="badge badge-ghost badge-xs">{{ $sample['category_hit'] ?? '-' }}</div>
                                            </td>
                                            <td>
                                                <div class="font-mono font-bold whitespace-nowrap {{ $isNearMissDirectly ? 'text-warning' : 'opacity-40' }}">
                                                    {{ $primaryPerf }}
                                                    @if($isNearMissDirectly)
                                                        <progress class="progress progress-warning w-24 h-1" value="{{ $directPath['diff_percent'] > 100 ? 100 - ($directPath['diff_percent'] - 100) * 10 : $directPath['diff_percent'] }}" max="100"></progress>
                                                    @endif
                                                </div>
                                                @foreach($paths as $path)
                                                    @if(isset($path['via_secondary']))
                                                        <div class="text-[10px] text-slate-500 whitespace-nowrap font-normal mt-1">
                                                            Via {{ $path['via_secondary'] }}: {{ $path['secondary_perf'] }}
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </td>
                                            <td>
                                                <div class="font-mono text-xs opacity-40 whitespace-nowrap">
                                                    {{ $primaryLimit }}
                                                </div>
                                                @foreach($paths as $path)
                                                    @if(isset($path['via_secondary']))
                                                        <div class="text-[9px] text-slate-400 whitespace-nowrap font-normal mt-1 leading-tight">
                                                            (Lim: {{ $path['secondary_limit'] }} sur {{ $path['via_secondary'] }})
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </td>
                                            <td class="align-top pt-2">
                                                <span class="badge {{ $sample['source'] === 'DB' ? 'badge-info' : 'badge-warning' }} badge-outline text-[10px] h-4">
                                                    {{ $sample['source'] }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-400 italic">
                            Aucun athlète dans la marge des 5%.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>
</div>
