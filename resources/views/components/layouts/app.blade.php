<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'CA Sion - Statistiques' }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet" />
    
    @vite('resources/css/app.css')
</head>
<body class="bg-base-200 min-h-screen font-sans">
    <header class="bg-white shadow-sm sticky top-0 z-40">
        <div class="container mx-auto px-4 h-16 flex items-center justify-between gap-4">
            <a href="/" class="text-xl font-bold tracking-tight text-gray-900 hover:text-primary transition-colors whitespace-nowrap">
                <h1>{{ $title ?? 'CA Sion - Statistiques' }}</h1>
            </a>
            
            <div class="flex-1 flex justify-center max-w-sm ml-auto">
                <livewire:athlete-search />
            </div>

            <button onclick="info_modal.showModal()" class="btn btn-ghost btn-circle btn-sm text-gray-400 hover:text-primary transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </button>
        </div>
    </header>

    <dialog id="info_modal" class="modal">
        <div class="modal-box max-w-2xl bg-white/95 backdrop-blur-md">
            <form method="dialog">
                <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
            </form>
            <h3 class="font-bold text-2xl mb-6 text-primary flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                À propos des données
            </h3>
            
            <div class="space-y-6 text-sm leading-relaxed text-gray-600">
                <section>
                    <h4 class="font-bold text-gray-900 mb-2 uppercase tracking-wider text-xs">Historique</h4>
                    <ul class="space-y-2 list-disc list-inside">
                        <li><strong>1997 - 2012</strong> : Base alimentée par René de Voogd.</li>
                        <li><strong>2000 - 2025</strong> : Alimentation Swiss Athletics via LaNet.</li>
                        <li><strong>Archives 1962 - 2010</strong> : Reprise d'anciennes bases ( approximations possibles).</li>
                    </ul>
                </section>

                <section class="bg-primary/5 p-4 rounded-xl border border-primary/10">
                    <h4 class="font-bold text-primary mb-2 uppercase tracking-wider text-xs">État de la base</h4>
                    <p>Les résultats sont globalement complets jusqu'en <strong>juin 2017</strong>.</p>
                </section>

                <section>
                    <h4 class="font-bold text-gray-900 mb-2 uppercase tracking-wider text-xs">Lacunes connues</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-base-200 p-3 rounded-lg">
                            <span class="font-bold block text-gray-900">2013</span>
                            Absence totale de données.
                        </div>
                        <div class="bg-base-200 p-3 rounded-lg">
                            <span class="font-bold block text-gray-900">2014</span>
                            Données partielles.
                        </div>
                    </div>
                </section>

                <div class="alert bg-base-100 border-base-300 text-xs">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-info shrink-0 w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>Si vous possédez des archives pour combler ces trous, vos annonces sont les bienvenues !</span>
                </div>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>

    <div class="container mx-auto py-8 px-4">
        {{ $slot }}
    </div>
</body>
</html>
