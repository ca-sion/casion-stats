<?php

namespace App\Livewire;

use App\Models\AthleteCategory;
use App\Models\Discipline;
use App\Services\HistoricalImportService;
use Livewire\Component;
use Livewire\WithFileUploads;

class ImportHistoricalData extends Component
{
    use WithFileUploads;

    public $csvFile;

    public $step = 1;

    public $parsedData = [];

    // Step 2: Mapping
    public $unmappedDisciplines = []; // ['german_name' => '']

    public $disciplineMappings = []; // User choices: ['german_name' => 'selected_fr_name']

    public $autoMappedDisciplines = []; // ['german_name' => 'fr_name']

    public $unmappedCategories = [];

    public $categoryMappings = [];

    public $autoMappedCategories = [];

    // Step 3: Resolution
    public $resolvedAthletes = []; // Array of ['data' => ..., 'status' => 'new'|'found'|'merged', 'athlete_id' => ...]

    // Progress
    public $importLogs = [];

    public $progress = 0;

    protected $service;

    public function boot(HistoricalImportService $service)
    {
        $this->service = $service;
    }

    public function mount()
    {
        if (! app()->isLocal() && ! app()->runningUnitTests()) {
            abort(403, 'Accès réservé à l\'environnement local.');
        }
    }

    public function updatedCsvFile()
    {
        $this->analyzeFile();
    }

    private function getParsedData(): array
    {
        if (! empty($this->parsedData)) {
            return $this->parsedData;
        }

        if (! $this->csvFile) {
            return [];
        }

        $content = null;
        if (is_object($this->csvFile) && method_exists($this->csvFile, 'readStream')) {
            try {
                $stream = $this->csvFile->readStream();
                if (is_resource($stream)) {
                    rewind($stream);
                    $content = stream_get_contents($stream);
                }
            } catch (\Throwable $e) {}
        }

        if (empty($content) && is_object($this->csvFile) && method_exists($this->csvFile, 'getRealPath')) {
            try {
                $path = $this->csvFile->getRealPath();
                if ($path && file_exists($path)) {
                    $content = file_get_contents($path);
                }
            } catch (\Throwable $e) {}
        }

        if (empty($content) && is_string($this->csvFile) && file_exists($this->csvFile)) {
            $content = file_get_contents($this->csvFile);
        }

        return $this->service->parseCsvString($content);
    }

    public function analyzeFile()
    {
        $this->service->clearCache();

        $this->parsedData = $this->getParsedData();

        $this->resolveAthletesFromData($this->parsedData);

        $this->step = 2;
    }

    private function resolveAthletesFromData($data)
    {
        // Find unmapped items
        $disciplines = collect($data)->pluck('raw_discipline')->unique();
        $categories = collect($data)->pluck('raw_category')->unique();

        $this->unmappedDisciplines = [];
        $this->autoMappedDisciplines = [];
        foreach ($disciplines as $german) {
            $model = $this->service->findDisciplineModel($german);
            if ($model) {
                $this->autoMappedDisciplines[$german] = $model->name_fr;
            } else {
                $this->unmappedDisciplines[$german] = ''; // Init empty selection
            }
        }

        $this->unmappedCategories = [];
        $this->autoMappedCategories = [];
        foreach ($categories as $german) {
            $model = $this->service->findCategoryModel($german);
            if ($model) {
                $this->autoMappedCategories[$german] = $model->name;
            } else {
                $this->unmappedCategories[$german] = '';
            }
        }

        $this->step = 2;
    }

    public function saveMappings()
    {
        // Save Disciplines
        foreach ($this->disciplineMappings as $german => $french) {
            if ($french) {
                $d = Discipline::where('name_fr', $french)->first();
                if ($d && empty($d->name_de)) {
                    $d->name_de = $german;
                    $d->save();
                }
            }
        }

        // Save Categories
        foreach ($this->categoryMappings as $german => $french) {
            if ($french) {
                $c = AthleteCategory::where('name', $french)->first();
                if ($c && empty($c->name_de)) {
                    $c->name_de = $german;
                    $c->save();
                }
            }
        }

        $this->resolveAthletes();
        $this->step = 3;
    }

    public function resolveAthletes()
    {
        $this->service->clearCache();

        $data = $this->getParsedData();

        // Pre-calculate status for all rows
        $this->resolvedAthletes = [];
        $seenInBatch = [];

        foreach ($data as $index => $row) {
            // 1. Resolve Athlete (Dry Run)
            [$athlete, $isNewAthlete] = $this->service->resolveAthlete($row, true);

            // 2. Resolve Discipline
            $dNameRaw = $row['raw_discipline'];
            $dNameMapped = $this->disciplineMappings[$dNameRaw] ?? null;
            $discipline = null;
            if ($dNameMapped) {
                $discipline = Discipline::where('name_fr', $dNameMapped)->first();
            } else {
                $discipline = $this->service->findDisciplineModel($dNameRaw) ?? $this->service->findOrMapDiscipline($dNameRaw);
            }

            // 3. Resolve Category
            $cNameRaw = $row['raw_category'];
            $cNameMapped = $this->categoryMappings[$cNameRaw] ?? null;
            $category = null;
            if ($cNameMapped) {
                $category = AthleteCategory::where('name', $cNameMapped)->first();
            } else {
                $category = $this->service->findCategoryModel($cNameRaw) ?? $this->service->findOrMapCategory($cNameRaw);
            }

            // 4. Check Result Status
            $resultStatus = 'error';
            if ($athlete && $discipline && $category) {
                $existsInDb = $this->service->checkResultExists($row, $athlete, $discipline, $category);

                // Check for in-batch duplicate (same athlete identity + discipline + date + normalized performance)
                $normPerf = $this->service->parsePerformanceToSeconds($row['performance']);
                $athleteKey = $athlete->id
                    ?? (! empty($row['license']) ? 'lic:'.$row['license'] : mb_strtolower($row['firstname'].'_'.$row['lastname'].'_'.($row['birthdate'] ?? '')));

                $batchKey = $athleteKey.'|'.($discipline->id ?? $dNameRaw).'|'.($row['date'] ?? '').'|'.($normPerf ?? $row['performance']);

                if ($existsInDb || isset($seenInBatch[$batchKey])) {
                    $resultStatus = 'duplicate';
                } else {
                    $resultStatus = 'new';
                    $seenInBatch[$batchKey] = true;
                }
            }

            $athleteName = $athlete ? trim($athlete->first_name.' '.$athlete->last_name) : trim($row['firstname'].' '.$row['lastname']);

            $this->resolvedAthletes[$index] = [
                'row' => $row,
                'athlete_status' => $isNewAthlete ? 'new' : 'found',
                'athlete_id' => $athlete?->id,
                'athlete_name' => $athleteName ?: '?',
                'result_status' => $resultStatus,
                'discipline_id' => $discipline?->id,
                'discipline_name' => $discipline?->name_fr ?? $dNameRaw,
                'category_id' => $category?->id,
                'category_name' => $category?->name ?? $cNameRaw,
                'is_selected' => ($resultStatus === 'new'), // Default select only new results
            ];
        }
    }

    public function executeImport()
    {
        $count = 0;
        $duplicatesCount = 0;
        $total = count($this->resolvedAthletes);

        $this->service->clearCache();

        foreach ($this->resolvedAthletes as $item) {
            if (! $item['is_selected']) {
                $duplicatesCount++;

                continue;
            }

            $row = $item['row'];

            // Re-resolve athlete with dryRun=false to persist
            [$athlete, $isNew] = $this->service->resolveAthlete($row, false);

            // Use IDs from mapping phase if possible
            $discipline = $item['discipline_id'] ? Discipline::find($item['discipline_id']) : null;
            $category = $item['category_id'] ? AthleteCategory::find($item['category_id']) : null;

            // Fallback to service mapping if IDs weren't resolved
            if (! $discipline) {
                $dNameRaw = $row['raw_discipline'];
                $dNameMapped = $this->disciplineMappings[$dNameRaw] ?? null;
                $discipline = $dNameMapped ? Discipline::where('name_fr', $dNameMapped)->first() : $this->service->findOrMapDiscipline($dNameRaw);
            }

            if (! $category) {
                $cNameRaw = $row['raw_category'];
                $cNameMapped = $this->categoryMappings[$cNameRaw] ?? null;
                $category = $cNameMapped ? AthleteCategory::where('name', $cNameMapped)->first() : $this->service->findOrMapCategory($cNameRaw);
            }

            if ($athlete && $discipline && $category) {
                $result = $this->service->importResult($row, $athlete, $discipline, $category);
                if ($result->wasRecentlyCreated) {
                    $count++;
                } else {
                    $duplicatesCount++;
                }
            }

            $this->progress = intval((($count + $duplicatesCount) / ($total ?: 1)) * 100);
        }

        $this->importLogs[] = "Import terminé ! $count nouveaux résultats importés, $duplicatesCount doublons ignorés/mis à jour.";
        $this->step = 4;
    }

    public function render()
    {
        return view('livewire.import-historical-data', [
            'availableDisciplines' => Discipline::orderBy('name_fr')->get(),
            'availableCategories' => AthleteCategory::orderByDesc('is_primary')->orderBy('name')->get(),
        ])->layout('components.layouts.app');
    }
}
