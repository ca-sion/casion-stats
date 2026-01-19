<div class="flex flex-col md:flex-row justify-center gap-6">

    <div>
        <div class="flex flex-col gap-2">
            <div x-data="{
                open: false,
                search: '',
                disciplines: @js($disciplines),
                get filteredDisciplines() {
                    if (this.search === '') return this.disciplines;
                    const s = this.search.toLowerCase();
                    return this.disciplines.filter(d => d.name.toLowerCase().includes(s));
                },
                select(id) {
                    $wire.set('disciplineId', id);
                    this.open = false;
                    this.search = '';
                }
            }" class="dropdown w-full" :class="open && 'dropdown-open'" @click.outside="open = false">
                <div tabindex="0" role="button" class="select select-bordered w-full flex items-center justify-between" @click="open = !open">
                    <span>{{ $disciplines->firstWhere('id', $disciplineId)?->name ?? 'Discipline' }}</span>
                </div>
                <div class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-full mt-1 border border-base-300">
                    <input type="text" x-model="search" placeholder="Rechercher..." class="input input-sm input-bordered mb-2 w-full" autofocus @click.stop x-ref="searchInput" x-effect="if(open) $nextTick(() => $refs.searchInput.focus())">
                    <ul class="max-h-60 overflow-y-auto flex flex-col gap-1">
                        <template x-for="discipline in filteredDisciplines" :key="discipline.id">
                            <li>
                                <a @click="select(discipline.id)" :class="discipline.id == {{ $disciplineId }} && 'menu-active'">
                                    <span x-text="discipline.name"></span>
                                </a>
                            </li>
                        </template>
                        <li x-show="filteredDisciplines.length === 0" class="p-2 text-center text-sm opacity-50">Aucun résultat</li>
                    </ul>
                </div>
            </div>
            <select class="select select-bordered w-full" wire:model.live="categoryId">
                <option value="">Choisir une catégorie</option>
                @foreach ($athleteCategories as $athleteCategory)
                <option value="{{ $athleteCategory->id }}">{{ $athleteCategory->name }}</option>
                @endforeach
            </select>
            <select class="select select-bordered w-full" wire:model.live="genre">
                <option value="">Choisir un genre</option>
                <option value="m">Homme</option>
                <option value="w">Femme</option>
            </select>
        </div>

        <div class="flex gap-2 mt-6 cursor-pointer">
            <input type="checkbox" class="toggle toggle-warning toggle-xs" id="fix" wire:model.live="fix" />
            <label class="label-text text-xs">Fix</label>
        </div>
    </div>

    <div class="overflow-x-auto">
        <div wire:loading class="absolute p-4">
            <span class="loading loading-spinner loading-md"></span>
        </div>
        <table class="table table-xs" wire:loading.class="opacity-50">
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
            <tr wire:key="{{ $result->id }}">
                <th>{{ $loop->iteration }}</th>
                <td>
                    <a href="{{ route('athletes.show', $result->athlete->id) }}" class="link link-hover text-slate-700 font-semibold">
                        {{ $result->athlete->first_name }} {{ $result->athlete->last_name }}
                    </a>
                </td>
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
