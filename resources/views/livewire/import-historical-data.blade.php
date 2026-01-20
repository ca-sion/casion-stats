<div class="p-6 bg-base-100 rounded-lg shadow-xl">
    <h2 class="text-2xl font-bold mb-6">Wizard d'Importation Historique</h2>

    <ul class="steps w-full mb-8">
        <li class="step {{ $step >= 1 ? 'step-primary' : '' }}">Upload</li>
        <li class="step {{ $step >= 2 ? 'step-primary' : '' }}">Mapping</li>
        <li class="step {{ $step >= 3 ? 'step-primary' : '' }}">Validation</li>
        <li class="step {{ $step >= 4 ? 'step-primary' : '' }}">Terminé</li>
    </ul>

    {{-- Step 1: Upload --}}
    @if($step === 1)
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 py-4">
            <div>
                <h3 class="font-bold text-lg mb-4">Instructions</h3>
                <div class="prose text-sm text-base-content/80">
                    <p>Le fichier CSV doit respecter un format strict pour l'analyse. Voici ce que le système attend :</p>
                    <ul>
                        <li>Une ligne d'entête (Id, Firstname, etc.).</li>
                        <li>Des lignes de séparation pour les disciplines: <code>#50m #Männer</code>.</li>
                        <li>Les colonnes doivent être séparées par des virgules.</li>
                    </ul>
                    <p class="mt-2 text-info">💡 Astuce : Exportez vos données depuis l'ancien système en format "Raw CSV".</p>
                </div>
            </div>

            <div class="flex flex-col items-center justify-center gap-4 bg-base-200 p-8 rounded-lg border-2 border-dashed border-base-300">
                <div class="text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 mx-auto text-primary mb-2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                    </svg>
                    <span class="font-bold">Sélectionner un fichier CSV</span>
                </div>
                <input type="file" wire:model="csvFile" class="file-input file-input-bordered file-input-primary w-full max-w-xs" />
                <div wire:loading wire:target="csvFile" class="text-info text-sm">Analyse du fichier en cours...</div>
                @error('csvFile') <span class="text-error text-sm">{{ $message }}</span> @enderror
            </div>
        </div>
    @endif

    {{-- Step 2: Mapping --}}
    @if($step === 2)
        <div class="space-y-6">
            <div class="alert alert-info shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <div>
                    <h3 class="font-bold">Pourquoi cette étape ?</h3>
                    <div class="text-xs">Le fichier contient des noms de disciplines (souvent en Allemand) que le système ne connaît pas encore. 
                    En les reliant maintenant, vous "apprenez" au système la traduction. <br>
                    <strong>Exemple :</strong> Si le fichier dit "Weitsprung", dites au système que cela correspond à "Longueur".</div>
                </div>
            </div>

            @if(count($unmappedDisciplines) > 0)
                <div class="card bg-base-200 shadow-sm border border-base-300">
                    <div class="card-body p-4">
                        <h3 class="font-bold text-lg mb-2 text-warning flex items-center gap-2">
                            <span>Disciplines Inconnues</span>
                            <span class="badge badge-warning badge-sm">{{ count($unmappedDisciplines) }}</span>
                        </h3>
                        <div class="grid grid-cols-1  gap-4">
                            @foreach($unmappedDisciplines as $german => $val)
                                <div class="flex items-center gap-4 bg-base-100 p-3 rounded">
                                    <div class="flex-1">
                                        <div class="text-xs uppercase text-base-content/50 font-bold">Fichier CSV (Source)</div>
                                        <div class="font-bold text-lg">{{ $german }}</div>
                                    </div>
                                    <div class="flex-none">
                                        👉 correspond à 👉
                                    </div>
                                    <div class="flex-1">
                                        <div class="text-xs uppercase text-base-content/50 font-bold">Base de Données (Destination)</div>
                                        <select class="select select-bordered select-sm w-full" wire:model="disciplineMappings.{{ $german }}">
                                            <option value="">-- Ignorer / Inconnu --</option>
                                            @foreach($availableDisciplines as $d)
                                                <option value="{{ $d->name_fr }}">{{ $d->name_fr }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            @if(count($unmappedCategories) > 0)
                <div class="card bg-base-200 shadow-sm border border-base-300">
                     <div class="card-body p-4">
                        <h3 class="font-bold text-lg mb-2 text-warning flex items-center gap-2">
                             <span>Catégories Inconnues</span>
                             <span class="badge badge-warning badge-sm">{{ count($unmappedCategories) }}</span>
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach($unmappedCategories as $german => $val)
                                <div class="form-control bg-base-100 p-3 rounded">
                                    <label class="label">
                                        <span class="label-text font-bold">{{ $german }}</span>
                                        <span class="label-text-alt">Source</span>
                                    </label>
                                    <select class="select select-bordered select-sm w-full" wire:model="categoryMappings.{{ $german }}">
                                        <option value="">-- Sélectionner --</option>
                                        @foreach($availableCategories as $c)
                                            <option value="{{ $c->name }}">{{ $c->name }}  (Lim: {{ $c->age_limit }} ans)</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            @if(count($autoMappedDisciplines) > 0 || count($autoMappedCategories) > 0)
                <div class="collapse collapse-arrow bg-base-200 border border-base-300 shadow-sm">
                    <input type="checkbox" /> 
                    <div class="collapse-title text-sm font-medium flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-success">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Correspondances automatiques trouvées ({{ count($autoMappedDisciplines) + count($autoMappedCategories) }})
                    </div>
                    <div class="collapse-content"> 
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-2 text-xs">
                            @foreach($autoMappedDisciplines as $german => $french)
                                <div class="flex justify-between border-b border-base-300 py-1">
                                    <span class="text-base-content/60 font-mono">{{ $german }}</span>
                                    <span class="font-bold text-success">{{ $french }} (Discipline)</span>
                                </div>
                            @endforeach
                            @foreach($autoMappedCategories as $german => $french)
                                <div class="flex justify-between border-b border-base-300 py-1">
                                    <span class="text-base-content/60 font-mono">{{ $german }}</span>
                                    <span class="font-bold text-success">{{ $french }} (Catégorie)</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            @if(count($unmappedDisciplines) === 0 && count($unmappedCategories) === 0)
                <div class="alert alert-success shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <div>
                        <h3 class="font-bold">Tout est prêt !</h3>
                        <div class="text-xs">Toutes les disciplines et catégories du fichier ont été reconnues automatiquement.</div>
                    </div>
                </div>
            @endif

            <div class="flex justify-between mt-8">
                 <button class="btn btn-ghost" wire:click="$set('step', 1)">
                    ← Retour
                </button>
                <button class="btn btn-primary" wire:click="saveMappings">
                    Suivant & Analyser les Athlètes
                </button>
            </div>
        </div>
    @endif

    {{-- Step 3: Validation --}}
    @if($step === 3)
        <div class="space-y-4">
            <div class="flex justify-between items-center">
                 <h3 class="font-bold text-lg">Résultat de l'analyse</h3>
                 <span class="badge badge-lg">{{ count($resolvedAthletes) }} lignes analysées</span>
            </div>
            
            <div class="alert alert-warning shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                <div class="text-sm">
                    <strong>Mode "Simulation" :</strong> Vérifiez les données ci-dessous. <br>
                    Les doublons (Gris) ne seront pas importés par défaut. Les nouvelles performances (Bleu) seront créées.
                </div>
            </div>

            <div class="overflow-x-auto h-96 border rounded-lg bg-base-100">
                <table class="table table-pin-rows table-sm">
                    <thead>
                        <tr>
                            <th class="w-10">
                                {{-- Checkbox header globally? No, let's keep it per row for now or add Alpine later --}}
                            </th>
                            <th>Statut Import</th>
                            <th>Athlète Concerné</th>
                            <th>Performance (Résultat)</th>
                            <th>Détails Événement</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($resolvedAthletes as $index => $item)
                            <tr class="hover {{ $item['result_status'] === 'duplicate' ? 'opacity-60 bg-base-200' : '' }}">
                                <td>
                                    <input type="checkbox" class="checkbox checkbox-xs" wire:model="resolvedAthletes.{{ $index }}.is_selected" />
                                </td>
                                <td>
                                    @if($item['result_status'] === 'duplicate')
                                        <span class="badge badge-ghost badge-sm gap-1">
                                            Doublon (Ignoré)
                                        </span>
                                    @elseif($item['result_status'] === 'new')
                                        <span class="badge badge-primary badge-sm gap-1">
                                            Nouvelle Perf.
                                        </span>
                                    @else
                                        <span class="badge badge-error badge-sm">Erreur</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <div class="font-bold">{{ $item['athlete_name'] }}</div>
                                        @if($item['athlete_status'] === 'new')
                                            <span class="badge badge-info badge-xs badge-outline">Nouveau</span>
                                        @else
                                            <span class="badge badge-success badge-xs badge-outline" title="Existe en BDD #{{ $item['athlete_id'] }}">Trouvé</span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-base-content/50">
                                        Né(e) {{ $item['row']['birthdate'] }} 
                                        @if($item['row']['license']) • Lic: {{ $item['row']['license'] }} @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="font-mono font-bold">{{ $item['row']['performance'] }}</div>
                                    <div class="text-xs">
                                        @if($item['discipline_name'])
                                            {{ $item['discipline_name'] }}
                                        @else
                                            <span class="text-error">{{ $item['row']['raw_discipline'] }}</span>
                                        @endif
                                        / 
                                        @if($item['category_name'])
                                            {{ $item['category_name'] }}
                                        @else
                                            {{ $item['row']['raw_category'] }}
                                        @endif
                                    </div>
                                </td>
                                <td class="text-xs">
                                    <div>{{ $item['row']['date'] }}</div>
                                    <div class="truncate max-w-[150px]" title="{{ $item['row']['location'] }}">{{ $item['row']['location'] }}</div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex justify-between items-center pt-4 border-t">
                <button class="btn btn-ghost" wire:click="$set('step', 2)">
                    ← Revoir le mapping
                </button>
                
                <div class="text-right">
                    <div class="text-xs text-base-content/60 mb-1">
                        {{ collect($resolvedAthletes)->where('is_selected', true)->count() }} lignes sélectionnées
                    </div>
                    <button class="btn btn-primary" wire:click="executeImport" wire:loading.attr="disabled">
                        Lancer l'Importation Réelle
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Step 4: Finish --}}
    @if($step === 4)
        <div class="text-center py-10">
            <div class="mb-4 text-success flex justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-24 h-24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h3 class="text-3xl font-bold text-success mb-2">Importation Terminée !</h3>
            <p class="text-base-content/60 mb-8">Les données ont été traitées avec succès.</p>
            
            @if(count($importLogs) > 0)
                <div class="mockup-code text-left mb-8 max-h-60 overflow-y-auto">
                    @foreach($importLogs as $log)
                        <pre data-prefix=">"><code>{{ $log }}</code></pre>
                    @endforeach
                </div>
            @endif
            
            <button class="btn btn-outline" wire:click="$set('step', 1)">
                Importer un nouveau fichier
            </button>
        </div>
    @endif
</div>
