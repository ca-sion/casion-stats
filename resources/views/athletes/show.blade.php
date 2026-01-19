<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $athlete->first_name }} {{ $athlete->last_name }} - CA Sion - Statistiques</title>
        @vite('resources/css/app.css')
    </head>
    <body class="antialiased bg-white dark:bg-gray-950 text-gray-900 dark:text-gray-100 font-sans">
        <div class="max-w-5xl mx-auto p-4 md:p-6">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-2xl font-bold">{{ $athlete->first_name }} {{ $athlete->last_name }}</h1>
                    <p class="text-xs text-gray-500 uppercase tracking-wider">
                        {{ $athlete->genre == 'm' ? 'Homme' : 'Femme' }} • 
                        @if($athlete->birthdate->year > 1900)
                            Né(e) en {{ $athlete->birthdate->format('Y') }}
                        @else
                            Date de naissance inconnue
                        @endif
                    </p>
                </div>
                <a href="{{ url()->previous() == url()->current() ? url('/') : url()->previous() }}" class="btn btn-sm btn-outline">
                    Retour
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                <!-- Records -->
                <div class="md:col-span-4">
                    <h2 class="text-sm font-bold border-b pb-1 mb-3 uppercase opacity-50">Records Personnels</h2>
                    <table class="table table-xs w-full">
                        <tbody>
                            @foreach ($personalBests as $pb)
                            <tr>
                                <td class="font-medium px-0">{{ $pb->discipline->name }}</td>
                                <td class="text-right font-bold text-slate-700 px-0">{{ $pb->performance }}</td>
                                <td class="text-right text-[10px] opacity-40 px-0">{{ $pb->event->date->format('Y') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Historique -->
                <div class="md:col-span-8">
                    <h2 class="text-sm font-bold border-b pb-1 mb-3 uppercase opacity-50">Historique</h2>
                    <table class="table table-xs table-zebra w-full">
                        <thead>
                            <tr class="opacity-60">
                                <th class="px-1">Date</th>
                                <th class="px-1">Discipline</th>
                                <th class="px-1">Perf</th>
                                <th class="px-1">Lieu</th>
                                <th class="px-1 text-right">Rang</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($results as $result)
                            <tr>
                                <td class="text-[10px] px-1">{{ $result->event->date->format('d.m.y') }}</td>
                                <td class="font-medium px-1">{{ $result->discipline->name }}</td>
                                <td class="font-bold text-slate-700 px-1">{{ $result->performance }}</td>
                                <td class="text-[10px] opacity-70 px-1 truncate max-w-[150px]">{{ $result->event->location }}</td>
                                <td class="text-right px-1">{{ $result->rank }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </body>
</html>
