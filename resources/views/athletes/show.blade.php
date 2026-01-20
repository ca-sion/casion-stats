<x-layouts.app :title="$athlete->first_name . ' ' . $athlete->last_name . ' - CA Sion'">
    <div class="max-w-4xl mx-auto px-4 py-8">
        <!-- Header minimal -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-10">
            <div>
                <a href="{{ url()->previous() == url()->current() ? url('/') : url()->previous() }}" class="text-xs uppercase tracking-widest text-gray-400 hover:text-primary transition-colors mb-2 inline-block font-bold">
                    ← Retour
                </a>
                <h1 class="text-3xl md:text-4xl font-extrabold tracking-tight text-gray-900 leading-none">
                    {{ $athlete->first_name }} <span class="text-primary">{{ $athlete->last_name }}</span>
                </h1>
                <div class="flex items-center gap-3 mt-3">
                    <span class="badge badge-sm badge-ghost font-medium text-[10px] uppercase tracking-wider">
                        {{ $athlete->genre == 'm' ? 'Homme' : 'Femme' }}
                    </span>
                    @if($athlete->birthdate->year > 1900)
                        <span class="badge badge-sm badge-ghost font-medium text-[10px] uppercase tracking-wider">
                            Né(e) en {{ $athlete->birthdate->format('Y') }}
                        </span>
                    @endif
                </div>
            </div>

            <!-- Stats rapides -->
            <div class="flex gap-8 border-l border-gray-100 pl-8 h-12 items-center">
                <div class="flex flex-col">
                    <span class="text-[10px] uppercase tracking-widest text-gray-400 font-bold">Performances</span>
                    <span class="text-xl font-bold text-gray-900 leading-none">{{ $totalPerformances }}</span>
                </div>
                @if($activityPeriod)
                <div class="flex flex-col">
                    <span class="text-[10px] uppercase tracking-widest text-gray-400 font-bold">Activité</span>
                    <span class="text-xl font-bold text-gray-900 leading-none">{{ $activityPeriod }}</span>
                </div>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">
            <!-- Records Personnels -->
            <div class="lg:col-span-12">
                <div class="flex items-center gap-4 mb-6">
                    <h2 class="text-xs font-bold uppercase tracking-[0.2em] text-gray-400 whitespace-nowrap">Records Personnels</h2>
                    <div class="h-[1px] w-full bg-gray-100"></div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="table table-xs w-full">
                        <thead>
                            <tr class="text-gray-400 border-b border-gray-100">
                                <th class="bg-transparent font-bold text-[10px] uppercase tracking-widest px-2 py-3">Discipline</th>
                                <th class="bg-transparent font-bold text-[10px] uppercase tracking-widest px-2 py-3">Performance</th>
                                <th class="bg-transparent font-bold text-[10px] uppercase tracking-widest px-2 py-3 text-center">Année</th>
                                <th class="bg-transparent font-bold text-[10px] uppercase tracking-widest px-2 py-3 text-right">Top 100</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach ($personalBests as $pb)
                            <tr class="group hover:bg-gray-50 transition-colors">
                                <td class="px-2 py-2">
                                    <a href="{{ url('/?d=' . $pb->discipline_id . '&g=' . $athlete->genre) }}" class="font-bold text-gray-900 hover:text-primary transition-colors decoration-dotted underline-offset-4 hover:underline">
                                        {{ $pb->discipline->name_fr }}
                                    </a>
                                </td>
                                <td class="px-2 py-2 font-mono font-bold text-primary text-sm whitespace-nowrap">
                                    {{ $pb->performance }}
                                </td>
                                <td class="px-2 py-2 text-center text-gray-500 text-[11px] font-medium">
                                    {{ $pb->event->date->format('Y') }}
                                </td>
                                <td class="px-2 py-2 text-right">
                                    <span class="inline-flex items-center justify-center min-w-[1.5rem] px-1.5 py-0.5 bg-white border border-gray-100 shadow-sm rounded-md text-[10px] font-bold {{ $pb->top100_rank <= 10 ? 'text-primary border-primary/20 bg-primary/5' : 'text-gray-500' }}">
                                        {{ $pb->top100_rank }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

            </div>

            <!-- Historique Complet -->
            <div class="lg:col-span-12 mt-4">
                <div class="flex items-center gap-4 mb-6">
                    <h2 class="text-xs font-bold uppercase tracking-[0.2em] text-gray-400 whitespace-nowrap">Historique des compétitions</h2>
                    <div class="h-[1px] w-full bg-gray-100"></div>
                </div>

                <div class="overflow-x-auto">
                    <table class="table table-xs w-full">
                        <thead>
                            <tr class="text-gray-400">
                                <th class="text-[10px] uppercase tracking-widest px-2">Date</th>
                                <th class="text-[10px] uppercase tracking-widest px-2">Discipline</th>
                                <th class="text-[10px] uppercase tracking-widest px-2">Perf</th>
                                <th class="text-[10px] uppercase tracking-widest px-2">Lieu</th>
                                <th class="text-[10px] uppercase tracking-widest px-2 text-right">Rang</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach ($results as $result)
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="text-[11px] text-gray-500 border-none px-2 whitespace-nowrap">
                                    {{ $result->event->date->format('d.m.Y') }}
                                </td>
                                <td class="border-none px-2">
                                    <a href="{{ url('/?d=' . $result->discipline_id . '&g=' . $athlete->genre) }}" class="font-bold text-gray-700 hover:text-primary transition-colors">
                                        {{ $result->discipline->name_fr }}
                                    </a>
                                </td>
                                <td class="font-mono font-bold text-gray-900 border-none px-2">
                                    {{ $result->performance }}
                                </td>
                                <td class="text-[11px] text-gray-500 border-none px-2 max-w-[200px] truncate">
                                    {{ $result->event->location }}
                                    <span class="opacity-50 font-normal italic"> - {{ $result->event->name }}</span>
                                </td>
                                <td class="text-right font-medium text-gray-400 border-none px-2">
                                    {{ $result->rank ?: '-' }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>

