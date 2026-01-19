<div class="relative w-full max-w-sm" x-data="{ open: false }" @click.away="open = false">
    <div class="relative">
        <input 
            type="text" 
            wire:model.live.debounce.300ms="query"
            @focus="open = true"
            @input="open = true"
            placeholder="Rechercher un athlète..." 
            class="input input-sm input-bordered w-full pl-10 pr-4 rounded-full bg-base-100 shadow-sm focus:shadow-md focus:border-primary transition-all duration-300 placeholder-gray-400 text-sm"
        />
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
        </div>
        
        <!-- Loading Indicator -->
        <div wire:loading class="absolute inset-y-0 right-0 pr-3 flex items-center">
            <span class="loading loading-spinner loading-xs text-primary"></span>
        </div>
    </div>

    <!-- Results Dropdown -->
    <div 
        x-show="open && $wire.query.length >= 2" 
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-2 scale-95"
        class="absolute z-50 mt-2 w-full bg-white/90 backdrop-blur-md rounded-xl shadow-xl border border-base-200 overflow-hidden"
        style="display: none;"
    >
        @if(count($results) > 0)
            <ul class="py-2">
                @foreach($results as $athlete)
                    <li>
                        <a 
                            href="{{ route('athletes.show', $athlete) }}" 
                            class="block px-4 py-2 hover:bg-primary/10 transition-colors duration-150 group"
                        >
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-medium text-gray-800 group-hover:text-primary transition-colors">
                                        {{ $athlete->first_name }} {{ $athlete->last_name }}
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        {{ $athlete->birthdate ? \Carbon\Carbon::parse($athlete->birthdate)->year : 'N/A' }}
                                    </p>
                                </div>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-300 group-hover:text-primary transition-colors transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="px-4 py-3 text-sm text-gray-500 text-center">
                Aucun résultat trouvé
            </div>
        @endif
    </div>
</div>
