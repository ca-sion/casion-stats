<div class="p-6 bg-base-100 rounded-lg shadow-xl">
    <h2 class="text-2xl font-bold mb-6">Déduplication des Athlètes</h2>

    @if(!$scanComplete)
        <div class="flex flex-col items-center py-10">
            <p class="mb-4 text-lg">Cet outil va scanner la base de données pour trouver des doublons potentiels (nom similaire, même année de naissance).</p>
            <button class="btn btn-primary btn-lg" wire:click="scan" wire:loading.attr="disabled">
                <span wire:loading.remove>Lancer le scan</span>
                <span wire:loading>Scan en cours...</span>
            </button>
        </div>
    @else
        <div class="mb-4 flex justify-between items-center">
            <h3 class="text-xl font-bold">Doublons potentiels trouvés : {{ count($clusters) }} groupes</h3>
            <button class="btn btn-sm btn-outline" wire:click="scan">Rescanner</button>
        </div>

        @if(count($clusters) === 0)
            <div class="alert alert-success">Aucun doublon détecté !</div>
        @else
            <div class="space-y-8">
                @foreach($clusters as $index => $cluster)
                    <div class="card bg-base-200 shadow-sm border border-base-300">
                        <div class="card-body p-4">
                            <div class="flex justify-between items-start mb-2">
                                <h4 class="card-title text-base">Groupe #{{ $index + 1 }}</h4>
                                <button class="btn btn-xs btn-ghost text-error" wire:click="ignore({{ $index }})">Ignorer ce groupe</button>
                            </div>
                            
                            <div class="overflow-x-auto">
                                <table class="table table-sm w-full bg-base-100 rounded-lg">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nom</th>
                                            <th>Naissance</th>
                                            <th>Licence</th>
                                            <th>Résultats</th>
                                            <th>Créé le</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($cluster as $athlete)
                                            <tr>
                                                <td class="font-mono text-xs">{{ $athlete['id'] }}</td>
                                                <td class="font-bold">{{ $athlete['name'] }}</td>
                                                <td>{{ $athlete['birthdate'] }}</td>
                                                <td>
                                                    @if($athlete['license'] !== '-')
                                                        <span class="badge badge-neutral">{{ $athlete['license'] }}</span>
                                                    @else
                                                        <span class="text-gray-400">-</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <span class="badge {{ $athlete['results_count'] > 0 ? 'badge-primary' : 'badge-ghost' }}">
                                                        {{ $athlete['results_count'] }}
                                                    </span>
                                                </td>
                                                <td class="text-xs text-gray-500">{{ $athlete['created_at'] }}</td>
                                                <td>
                                                    <div class="dropdown dropdown-left">
                                                        <div tabindex="0" role="button" class="btn btn-xs btn-primary">Fusionner ici</div>
                                                        <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52">
                                                            <li class="menu-title">Fusionner les autres vers cet athlète (ID: {{ $athlete['id'] }})</li>
                                                            @foreach($cluster as $other)
                                                                @if($other['id'] !== $athlete['id'])
                                                                    <li>
                                                                        <button wire:click="merge({{ $athlete['id'] }}, {{ $other['id'] }}, {{ $index }})" 
                                                                                wire:confirm="Êtes-vous sûr de vouloir fusionner {{ $other['name'] }} ({{ $other['id'] }}) VERS {{ $athlete['name'] }} ({{ $athlete['id'] }}) ? Cette action est irréversible.">
                                                                            Absorber {{ $other['name'] }} ({{ $other['id'] }})
                                                                        </button>
                                                                    </li>
                                                                @endif
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</div>
