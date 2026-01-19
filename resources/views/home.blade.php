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
                <h1 class="text-3xl font-bold text-center">CA Sion - Statistiques (➝ 2016)</h1>
            </div>

            <div class="max-w-7xl mx-auto p-6 lg:p-8">
                <livewire:stats-table />
            </div>

        </div>
    </body>
</html>
