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
        <div class="flex flex-col items-center gap-4 py-8">
            <input type="file" wire:model="csvFile" class="file-input file-input-bordered file-input-primary w-full max-w-xs" />
            <div wire:loading wire:target="csvFile" class="text-info">Analyse du fichier en cours...</div>
            @error('csvFile') <span class="text-error">{{ $message }}</span> @enderror
        </div>
    @endif

    {{-- Step 2: Mapping --}}
    @if($step === 2)
        <div class="space-y-6">
            @if(count($unmappedDisciplines) > 0)
                <div class="card bg-base-200 p-4">
                    <h3 class="font-bold text-lg mb-2 text-warning">Disciplines Inconnues</h3>
                    <p class="text-sm mb-4">Associez les termes allemands aux disciplines françaises.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($unmappedDisciplines as $german => $val)
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text font-bold">{{ $german }}</span>
                                </label>
                                <select class="select select-bordered" wire:model="disciplineMappings.{{ $german }}">
                                    <option value="">-- Sélectionner --</option>
                                    @foreach($availableDisciplines as $d)
                                        <option value="{{ $d->name_fr }}">{{ $d->name_fr }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if(count($unmappedCategories) > 0)
                <div class="card bg-base-200 p-4">
                    <h3 class="font-bold text-lg mb-2 text-warning">Catégories Inconnues</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($unmappedCategories as $german => $val)
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text font-bold">{{ $german }}</span>
                                </label>
                                <select class="select select-bordered" wire:model="categoryMappings.{{ $german }}">
                                    <option value="">-- Sélectionner --</option>
                                    @foreach($availableCategories as $c)
                                        <option value="{{ $c->name }}">{{ $c->name }}  ({{ $c->age_limit }} ans)</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if(count($unmappedDisciplines) === 0 && count($unmappedCategories) === 0)
                <div class="alert alert-success">
                    <span>Tout semble mappé correctement !</span>
                </div>
            @endif

            <div class="flex justify-end">
                <button class="btn btn-primary" wire:click="saveMappings">Suivant & Analyser les Athlètes</button>
            </div>
        </div>
    @endif

    {{-- Step 3: Validation --}}
    @if($step === 3)
        <div class="space-y-4">
            <h3 class="font-bold text-lg">Athlètes détectés : {{ count($resolvedAthletes) }}</h3>
            
            <div class="overflow-x-auto h-96 border rounded-lg">
                <table class="table table-pin-rows">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Statut</th>
                            <th>CSV Nom</th>
                            <th>CSV Date</th>
                            <th>Licence</th>
                            <th>Match BDD</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($resolvedAthletes as $index => $item)
                            <tr>
                                <td>
                                    <input type="checkbox" class="checkbox checkbox-xs" wire:model="resolvedAthletes.{{ $index }}.is_selected" />
                                </td>
                                <td>
                                    @if($item['status'] === 'new')
                                        <span class="badge badge-info">Nouveau</span>
                                    @else
                                        <span class="badge badge-success">Trouvé</span>
                                    @endif
                                </td>
                                <td>{{ $item['row']['firstname'] }} {{ $item['row']['lastname'] }}</td>
                                <td>{{ $item['row']['birthdate'] }}</td>
                                <td>{{ $item['row']['license'] }}</td>
                                <td>
                                    @if($item['athlete'])
                                        {{ $item['athlete']->first_name }} {{ $item['athlete']->last_name }} ({{ $item['athlete']->id }})
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex justify-between items-center bg-base-200 p-4 rounded text-warning">
                <span>⚠️ Attention : Les athlètes marqués "Nouveau" ont déjà été créés en base lors de l'analyse. L'import créera uniquement les résultats.</span>
            </div>

            <div class="flex justify-end gap-2">
                <button class="btn btn-primary" wire:click="executeImport" wire:loading.attr="disabled">
                    Lancer l'Importation
                </button>
            </div>
        </div>
    @endif

    {{-- Step 4: Finish --}}
    @if($step === 4)
        <div class="text-center py-10">
            <h3 class="text-3xl font-bold text-success mb-4">Importation Terminée !</h3>
            @if(count($importLogs) > 0)
                <div class="mockup-code text-left">
                    @foreach($importLogs as $log)
                        <pre data-prefix=">"><code>{{ $log }}</code></pre>
                    @endforeach
                </div>
            @endif
            <button class="btn btn-outline mt-8" wire:click="$set('step', 1)">Nouvel Import</button>
        </div>
    @endif
</div>
