<div class="min-h-screen pb-20 animate-in fade-in duration-1000 bg-slate-50" wire:init="loadData">
    {{-- Magnificent Space Header & Podium Area --}}
    <div class="relative overflow-hidden bg-[#050510] lg:rounded-[3rem] lg:mx-4 lg:mt-4 p-8 lg:p-16 shadow-2xl">
        {{-- Deep Space Background Elements --}}
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            {{-- Starfield --}}
            <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/stardust.png')] opacity-30"></div>
            {{-- Nebula --}}
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[150%] h-[150%] bg-[radial-gradient(circle_at_center,rgba(79,70,229,0.15)_0%,rgba(139,92,246,0.05)_30%,transparent_60%)] animate-nebula"></div>
            <div class="absolute top-0 right-0 w-[50%] h-[50%] bg-[radial-gradient(circle_at_top_right,rgba(236,72,153,0.1)_0%,transparent_70%)]"></div>
        </div>

        {{-- Shooting Stars Container --}}
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="star-stream star-1"></div>
            <div class="star-stream star-2"></div>
            <div class="star-stream star-3"></div>
            <div class="star-stream star-4"></div>
        </div>

        <div class="relative z-10 flex flex-col lg:flex-row justify-between items-center gap-8 mb-16">
            <div class="text-center lg:text-left">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 backdrop-blur-md border border-white/20 text-white text-[10px] font-black uppercase tracking-widest mb-4">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-yellow-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-yellow-400"></span>
                    </span>
                    Club Leaderboard ⚡️
                </div>
                <h1 class="text-4xl lg:text-6xl font-black text-white tracking-tight mb-2 drop-shadow-lg">
                    Le Mur des <span class="text-yellow-300 italic">Légendes</span>
                </h1>
                <p class="text-indigo-100 text-sm lg:text-base font-medium max-w-xl opacity-80">
                    Découvrez les meilleures performances de l'histoire du club, toutes disciplines confondues.
                </p>
            </div>

            {{-- Controls / Filters --}}
            <div class="flex flex-col gap-4 w-full lg:w-auto">
                <div class="bg-black/20 backdrop-blur-xl border border-white/10 p-4 rounded-2xl shadow-xl flex flex-col sm:flex-row gap-4">
                    <div class="flex bg-black/40 p-1 rounded-xl shadow-inner min-w-[150px] border border-white/5">
                        <button wire:click="setGenre('')" class="flex-1 py-1.5 px-3 text-xs font-bold rounded-lg transition-all duration-300 {{ $genre === '' ? 'bg-white text-indigo-900 shadow-xl' : 'text-white/60 hover:text-white' }}">TOUS</button>
                        <button wire:click="setGenre('m')" class="flex-1 py-1.5 px-3 text-xs font-bold rounded-lg transition-all duration-300 {{ $genre === 'm' ? 'bg-white text-blue-900 shadow-xl' : 'text-white/60 hover:text-white' }}">HOMMES</button>
                        <button wire:click="setGenre('w')" class="flex-1 py-1.5 px-3 text-xs font-bold rounded-lg transition-all duration-300 {{ $genre === 'w' ? 'bg-white text-pink-900 shadow-xl' : 'text-white/60 hover:text-white' }}">FEMMES</button>
                    </div>

                    <div class="relative min-w-[200px]">
                        <select wire:model.live="categoryId" class="w-full bg-black/40 text-white border border-white/10 rounded-xl px-4 py-2.5 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-yellow-400/50 transition-all cursor-pointer appearance-none">
                            <option value="" class="bg-[#050510]">Toutes les catégories</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" class="bg-[#050510]">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                        <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-white/40">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Loading and Podium inside the Space Block --}}
        <div wire:loading.flex class="flex-col items-center justify-center py-20 animate-in fade-in zoom-in duration-500">
            <div class="relative mb-8">
                <div class="absolute inset-0 bg-indigo-500 rounded-full blur-3xl opacity-30 animate-pulse"></div>
                <div class="relative flex items-center gap-4 text-6xl">
                    <span class="animate-bounce" style="animation-delay: 0.1s">🚀</span>
                    <span class="animate-bounce" style="animation-delay: 0.2s">✨</span>
                    <span class="animate-bounce" style="animation-delay: 0.3s">🥈</span>
                    <span class="animate-bounce" style="animation-delay: 0.35s">🥇</span>
                    <span class="animate-bounce" style="animation-delay: 0.4s">🥉</span>
                    <span class="animate-bounce" style="animation-delay: 0.45s">🏁</span>
                </div>
            </div>
            <h2 class="text-xl font-bold text-white tracking-widest uppercase">Analyse des performances en cours…</h2>
        </div>

        @if($readyToLoad)
            <div wire:loading.remove>
                @if(count($results) >= 3)
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 items-end max-w-6xl mx-auto px-4 perspective relative z-10">
                    {{-- Second Place --}}
                    @php $second = $results->values()[1]; @endphp
                    <div class="order-2 md:order-1 flex flex-col items-center animate-in slide-in-from-bottom-8 duration-700 delay-200 podium-pulse">
                         <a href="{{ route('athletes.show', $second->athlete_id) }}" class="relative group mb-4 block hover:scale-105 transition-transform">
                            <div class="absolute inset-0 bg-slate-400 rounded-full blur-2xl opacity-20 group-hover:opacity-40 transition-opacity"></div>
                            <div class="relative w-28 h-28 rounded-full bg-white/10 backdrop-blur-xl border-4 border-slate-300 shadow-2xl flex items-center justify-center overflow-hidden">
                                <div class="absolute inset-0 bg-gradient-to-tr from-slate-500/20 to-transparent"></div>
                                <span class="text-4xl relative z-10">🥈</span>
                            </div>
                        </a>
                        <div class="bg-white/10 backdrop-blur-2xl rounded-3xl p-6 border border-white/20 shadow-2xl w-full text-center relative overflow-hidden group">
                            <div class="absolute inset-0 bg-gradient-to-br from-slate-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                            <h3 class="font-black text-xl text-white truncate mb-1">{{ $second->athlete->first_name }}</h3>
                            <h4 class="font-bold text-sm text-indigo-200/60 truncate mb-3 uppercase tracking-tighter">{{ $second->athlete->last_name }}</h4>
                            <div class="text-[10px] font-black text-indigo-400 uppercase tracking-widest mb-4 bg-white/10 inline-block px-2 py-0.5 rounded-md border border-white/10">{{ $second->discipline->name_fr }}</div>
                            
                            <div class="flex flex-col items-center gap-2">
                                <span class="px-3 py-1 rounded-full bg-white/10 text-white font-black text-xs tabular-nums border border-white/20 shadow-sm">
                                    {{ $second->performance }}
                                </span>
                                <div class="bg-gradient-to-r from-slate-500 to-slate-600 px-4 py-1.5 rounded-2xl text-white font-black text-xl tabular-nums shadow-lg shadow-black/20 italic">
                                    {{ $second->iaaf_points }} <span class="text-[10px] uppercase ml-0.5 not-italic opacity-70 font-bold">pts</span>
                                </div>
                            </div>
                        </div>
                        <div class="h-12 w-full max-w-[180px] bg-white/5 backdrop-blur-sm mt-2 rounded-t-3xl border-x border-t border-white/10 shadow-inner"></div>
                    </div>

                    {{-- First Place --}}
                    @php $first = $results->values()[0]; @endphp
                    <div class="order-1 md:order-2 flex flex-col items-center animate-in slide-in-from-bottom-12 duration-1000 podium-pulse-fast">
                        <a href="{{ route('athletes.show', $first->athlete_id) }}" class="relative group mb-6 block hover:scale-105 transition-transform">
                            <div class="absolute inset-0 bg-yellow-400 rounded-full blur-3xl opacity-30 animate-pulse"></div>
                            <div class="relative w-36 h-36 rounded-full bg-white/10 backdrop-blur-xl border-4 border-yellow-400 shadow-[0_0_50px_-12px_rgba(250,204,21,0.5)] flex items-center justify-center overflow-hidden">
                                <div class="absolute inset-0 bg-gradient-to-tr from-yellow-400/30 to-transparent"></div>
                                <span class="text-6xl drop-shadow-2xl relative z-10">🏆</span>
                            </div>
                        </a>
                        <div class="bg-white/20 backdrop-blur-3xl rounded-[2.5rem] p-8 border-2 border-white/30 shadow-2xl w-full text-center transform scale-110 relative overflow-hidden group">
                            <div class="relative z-10">
                                <h3 class="font-black text-3xl text-white truncate mb-1 tracking-tight leading-none">{{ $first->athlete->first_name }}</h3>
                                <h4 class="font-black text-xl text-indigo-100 truncate mb-4 uppercase tracking-tighter opacity-80">{{ $first->athlete->last_name }}</h4>
                                <div class="text-xs font-black text-yellow-300 uppercase tracking-[0.2em] mb-5 bg-white/10 inline-block px-3 py-1 rounded-lg border border-white/20">{{ $first->discipline->name_fr }}</div>
                                
                                <div class="flex flex-col items-center gap-3">
                                    <span class="px-4 py-1.5 rounded-2xl bg-white shadow-xl text-indigo-900 font-black text-sm tabular-nums border border-indigo-50">
                                        {{ $first->performance }}
                                    </span>
                                    <div class="bg-gradient-to-r from-yellow-400 via-yellow-500 to-yellow-600 text-indigo-950 px-8 py-3 rounded-3xl font-black text-4xl tabular-nums shadow-2xl shadow-yellow-500/20 italic ring-4 ring-white/20">
                                        {{ $first->iaaf_points }} <span class="text-sm uppercase ml-1 not-italic opacity-80 font-bold">pts</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="h-20 w-full max-w-[220px] bg-gradient-to-t from-yellow-500/20 to-white/5 backdrop-blur-md mt-4 rounded-t-[2.5rem] border-x border-t border-white/20 shadow-2xl"></div>
                    </div>

                    {{-- Third Place --}}
                    @php $third = $results->values()[2]; @endphp
                    <div class="order-3 md:order-3 flex flex-col items-center animate-in slide-in-from-bottom-8 duration-700 delay-400 podium-pulse">
                         <a href="{{ route('athletes.show', $third->athlete_id) }}" class="relative group mb-4 block hover:scale-105 transition-transform">
                            <div class="absolute inset-0 bg-orange-400 rounded-full blur-2xl opacity-20 group-hover:opacity-40 transition-opacity"></div>
                            <div class="relative w-28 h-28 rounded-full bg-white/10 backdrop-blur-xl border-4 border-orange-300 shadow-2xl flex items-center justify-center overflow-hidden">
                                <div class="absolute inset-0 bg-gradient-to-tr from-orange-500/20 to-transparent"></div>
                                <span class="text-4xl relative z-10">🥉</span>
                            </div>
                        </a>
                        <div class="bg-white/10 backdrop-blur-2xl rounded-3xl p-6 border border-white/20 shadow-2xl w-full text-center relative overflow-hidden group">
                            <div class="absolute inset-0 bg-gradient-to-br from-orange-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                            <h3 class="font-black text-xl text-white truncate mb-1">{{ $third->athlete->first_name }}</h3>
                            <h4 class="font-bold text-sm text-indigo-200/60 truncate mb-3 uppercase tracking-tighter">{{ $third->athlete->last_name }}</h4>
                            <div class="text-[10px] font-black text-orange-400 uppercase tracking-widest mb-4 bg-white/10 inline-block px-2 py-0.5 rounded-md border border-white/10">{{ $third->discipline->name_fr }}</div>
                            
                            <div class="flex flex-col items-center gap-2">
                                <span class="px-3 py-1 rounded-full bg-white/10 text-white font-black text-xs tabular-nums border border-white/20 shadow-sm">
                                    {{ $third->performance }}
                                </span>
                                <div class="bg-gradient-to-r from-orange-500 to-orange-600 px-4 py-1.5 rounded-2xl text-white font-black text-xl tabular-nums shadow-lg shadow-black/20 italic">
                                    {{ $third->iaaf_points }} <span class="text-[10px] uppercase ml-0.5 not-italic opacity-70 font-bold">pts</span>
                                </div>
                            </div>
                        </div>
                        <div class="h-8 w-full max-w-[180px] bg-white/5 backdrop-blur-sm mt-2 rounded-t-3xl border-x border-t border-white/10 shadow-inner"></div>
                    </div>
                </div>
                @endif
            </div>
        @endif
    </div>

        @if($readyToLoad && count($results) > 0)
        <div wire:loading.remove>
            {{-- The List --}}
            @php $maxPoints = $results->first()?->iaaf_points ?? 1200; @endphp
            <div class="max-w-6xl mx-auto px-4 mt-12 pb-24">
                <div class="bg-white/80 backdrop-blur-3xl rounded-[2.5rem] border border-white/40 shadow-2xl overflow-hidden mb-12">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-separate border-spacing-0">
                            <thead>
                                <tr class="bg-slate-50/50">
                                    <th class="px-8 py-6 text-[10px] font-black uppercase tracking-[0.3em] text-slate-400 border-b border-indigo-50">Rank</th>
                                    <th class="px-8 py-6 text-[10px] font-black uppercase tracking-[0.3em] text-slate-400 border-b border-indigo-50">Légende</th>
                                    <th class="px-8 py-6 text-[10px] font-black uppercase tracking-[0.3em] text-slate-400 border-b border-indigo-50">Discipline</th>
                                    <th class="px-8 py-6 text-[10px] font-black uppercase tracking-[0.3em] text-slate-400 border-b border-indigo-50 text-center">Perf.</th>
                                    <th class="px-8 py-6 text-[10px] font-black uppercase tracking-[0.3em] text-slate-400 border-b border-indigo-50 text-center">Année</th>
                                    <th class="px-8 py-6 text-[10px] font-black uppercase tracking-[0.3em] text-slate-400 border-b border-indigo-50 text-right">IAA Points</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-indigo-50/50">
                                @forelse($results->skip(count($results) >= 3 ? 3 : 0) as $index => $res)
                                @php $actualRank = $loop->iteration + (count($results) >= 3 ? 3 : 0); @endphp
                                <tr class="group hover:bg-indigo-50/30 transition-all duration-300 animate-in fade-in slide-in-from-left-4" style="animation-delay: {{ $index * 20 }}ms">
                                    <td class="px-8 py-5">
                                        <div class="relative inline-flex items-center justify-center w-10 h-10">
                                            <div class="absolute inset-0 bg-indigo-500/10 rounded-xl rotate-3 group-hover:rotate-6 group-hover:bg-indigo-600 transition-all"></div>
                                            <span class="relative z-10 text-indigo-600 font-black text-sm tabular-nums group-hover:text-white transition-colors">
                                                {{ $actualRank }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-8 py-5">
                                        <div class="flex flex-col">
                                            <a href="{{ route('athletes.show', $res->athlete_id) }}" class="text-base font-black text-slate-800 tracking-tight hover:text-indigo-600 transition-colors">{{ $res->athlete->first_name }} {{ $res->athlete->last_name }}</a>
                                            <span class="text-[9px] font-bold text-indigo-400/60 uppercase tracking-widest">{{ $res->athleteCategory->name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-8 py-5">
                                        <span class="text-xs font-black text-slate-500 group-hover:text-slate-700 transition-colors uppercase tracking-tighter">{{ $res->discipline->name_fr }}</span>
                                    </td>
                                    <td class="px-8 py-5 text-center">
                                        <span class="inline-flex px-3 py-1 rounded-xl bg-white/50 text-slate-700 font-black text-xs tabular-nums group-hover:bg-indigo-600 group-hover:text-white transition-all border border-indigo-50/50">
                                            {{ $res->performance }}
                                        </span>
                                    </td>
                                    <td class="px-8 py-5 text-center">
                                        <span class="text-xs font-bold text-slate-400 tabular-nums">
                                            {{ $res->event?->date?->format('Y') ?? '?' }}
                                        </span>
                                    </td>
                                    <td class="px-8 py-5 text-right">
                                        <div class="flex items-center justify-end gap-3">
                                            <div class="w-40 bg-slate-100 h-2 rounded-full overflow-hidden hidden md:flex border border-slate-200 relative shadow-inner">
                                                @php $percent = ($res->iaaf_points / $maxPoints) * 100; @endphp
                                                <div class="h-full bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 rounded-full group-hover:animate-pulse transition-all duration-1000" style="width:{{ $percent }}%"></div>
                                                <div class="absolute inset-0 bg-[linear-gradient(90deg,transparent_0%,rgba(255,255,255,0.2)_50%,transparent_100%)] animate-shimmer"></div>
                                            </div>
                                            <div class="flex items-baseline gap-0.5">
                                                <span class="text-lg font-black text-transparent bg-clip-text bg-gradient-to-r from-indigo-600 to-purple-600 italic tracking-tighter tabular-nums">{{ $res->iaaf_points }}</span>
                                                <span class="text-[9px] font-black text-indigo-300 uppercase italic">pts</span>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="100" class="py-32 text-center text-slate-300">
                                        <div class="flex flex-col items-center">
                                            <div class="text-8xl mb-6 filter grayscale opacity-20">🏆</div>
                                            <p class="text-xl font-black tracking-tight italic">Aucune légende n'est encore née ici...</p>
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                
                @if(count($results) >= $this->limit)
                <div class="mt-8 text-center">
                    <button wire:click="showMore" class="group relative px-10 py-4 bg-gradient-to-r from-indigo-600 to-purple-600 rounded-[2rem] text-white font-black text-xs uppercase tracking-[0.2em] shadow-2xl transition-all hover:scale-105 active:scale-95 overflow-hidden">
                        <div class="absolute inset-0 bg-white/20 translate-y-12 group-hover:translate-y-0 transition-transform duration-300"></div>
                        <span class="relative z-10 flex items-center gap-3">Afficher plus de légendes <span class="group-hover:translate-x-1 transition-transform">🚀</span></span>
                    </button>
                </div>
                @endif
            </div>
        </div>
        @endif

    <style>
        .podium-pulse {
            animation: podium-pulse 6s ease-in-out infinite;
        }
        .podium-pulse-fast {
            animation: podium-pulse 5s ease-in-out infinite;
        }
        @keyframes podium-pulse {
            0%, 100% { transform: scale(1) translateY(0); }
            50% { transform: scale(1.01) translateY(-5px); }
        }
        .star-stream {
            position: absolute;
            top: -100px;
            left: -100px;
            width: 2px;
            height: 2px;
            background: #fff;
            border-radius: 50%;
            box-shadow: 0 0 10px #fff, 0 0 20px #fff;
            opacity: 0;
        }
        .star-1 { top: 10%; animation: shoot 5s 1s infinite; }
        .star-2 { top: 30%; animation: shoot 7s 3s infinite; }
        .star-3 { top: 60%; animation: shoot 4s 0s infinite; }
        .star-4 { top: 85%; animation: shoot 6s 4s infinite; }

        @keyframes shoot {
            0% { transform: translateX(0) translateY(0) scale(0); opacity: 0; }
            5% { opacity: 1; scale(1); }
            15% { transform: translateX(1200px) translateY(400px) scale(0.5); opacity: 0; }
            100% { transform: translateX(1200px) translateY(400px) scale(0); opacity: 0; }
        }
        @keyframes nebula {
            0%, 100% { transform: translate(-50%, -50%) scale(1); opacity: 0.5; }
            50% { transform: translate(-50%, -52%) scale(1.1); opacity: 0.8; }
        }
        .animate-nebula {
            animation: nebula 15s ease-in-out infinite;
        }
        .animate-shimmer {
            animation: shimmer 2s linear infinite;
        }
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        @keyframes pulse {
            0%, 100% { opacity: 0.3; }
            50% { opacity: 0.5; }
        }
        .perspective {
            perspective: 1000px;
        }
    </style>
</div>
