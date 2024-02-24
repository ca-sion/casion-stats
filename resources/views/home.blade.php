<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>CA Sion - Statistiques</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet" />

        <!-- Styles -->
        @vite('resources/css/app.css')
    </head>
    <body class="antialiased">
        <div class="bg-gray-50 dark:bg-gray-900 selection:bg-red-500 selection:text-white">

            <div class="py-4">
                <h1 class="text-3xl font-bold text-center">CA Sion - Statistiques (➝ 2014)</h1>
            </div>

            <div class="max-w-7xl mx-auto p-6 lg:p-8">
                <div class="flex flex-col md:flex-row justify-center gap-6">

                    <div>
                        <form action="" method="get" class="flex flex-col gap-2">
                            <select class="select select-bordered w-full" onchange="this.form.submit()" name="d">
                                <option disabled selected>Choisir une discipline</option>
                                @foreach ($disciplines as $discipline)
                                <option value="{{ $discipline->id }}" @selected($discipline->id == $d)>{{ $discipline->name }}</option>
                                @endforeach
                            </select>
                            <select class="select select-bordered w-full" onchange="this.form.submit()" name="ac">
                                <option disabled selected>Choisir une catégorie</option>
                                <option value="">-</option>
                                @foreach ($athleteCategories as $athleteCategory)
                                <option value="{{ $athleteCategory->id }}" @selected($athleteCategory->id == $ac)>{{ $athleteCategory->name }}</option>
                                @endforeach
                            </select>
                            <select class="select select-bordered w-full" onchange="this.form.submit()" name="g">
                                <option disabled selected>Choisir un genre</option>
                                <option value="">-</option>
                                <option value="m" @selected('m' == $g)>Homme</option>
                                <option value="w" @selected('w' == $g)>Femme</option>
                            </select>
                            <button type="submit" class="btn">Chercher</button>
                        </form>

                        <form action="" method="get">
                            <div class="flex gap-2 mt-6 cursor-pointer">
                                <input type="hidden" name="fix" value="off">
                                <input type="checkbox" class="toggle toggle-warning toggle-xs" @checked($isFix) id="fix" name="fix" onchange="this.form.submit()" value="on" />
                                <label class="label-text text-xs">Fix</label>
                            </div>
                        </form>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="table table-xs">
                          <thead>
                            <tr>
                              <th></th>
                              <th>Athlète</th>
                              <th>Performance</th>
                              <th>Année</th>
                              <th>Compétition</th>
                              <th>Lieu</th>
                              <th>Rang</th>
                              @if ($isFix)
                              <th>Date</th>
                              <th>ID</th>
                              <th>ID: athlète</th>
                              <th>Genre</th>
                              <th>Contrôle</th>
                              @endif
                            </tr>
                          </thead>
                          <tbody>
                            @foreach ($results as $result)
                            <tr>
                                <th>{{ $loop->iteration }}</th>
                                <td>{{ $result->athlete->first_name }} {{ $result->athlete->last_name }}</td>
                                <td>{{ $result->performance }}</td>
                                <td>{{ $result->event->date->format('Y') }}</td>
                                <td>{{ $result->event->name }}</td>
                                <td>{{ $result->event->location }}</td>
                                <td>{{ $result->rank }}</td>
                                @if ($isFix)
                                <td>{{ $result->event->date->format('d.m.Y') }}</td>
                                <td>{{ $result->id }}</td>
                                <td>{{ $result->athlete->id }}</td>
                                <td>{{ $result->athlete->genre }}</td>
                                <td>
                                    @if ($result->athlete->genre != $result->athleteCategory->genre)
                                    <div class="text-orange-700">{{ $result->athlete->genre }}≠{{ $result->athleteCategory->genre }}</div>
                                    @endif
                                    @if ($result->athlete->birthdate->diffInYears($result->event->date) > $result->athleteCategory->age_limit)
                                    <div class="text-orange-700">Cat</div>
                                    @endif
                                </td>
                                @endif
                              </tr>
                            @endforeach
                          </tbody>
                        </table>
                      </div>
                </div>
            </div>

        </div>
    </body>
</html>
