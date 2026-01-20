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
        if (! app()->isLocal()) {
            abort(403, 'Accès réservé à l\'environnement local.');
        }
    }

    public function updatedCsvFile()
    {
        $this->validate([
            'csvFile' => 'required|file|mimes:csv,txt|max:10240', // 10MB
        ]);
        
        $this->analyzeFile();
    }

    public function analyzeFile()
    {
        $path = $this->csvFile->getRealPath();
        $data = $this->service->parseCsv($path);
        
        $this->resolveAthletesFromData($data);
        
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
                // Find the discipline by french name (name_fr) to get the model
                $d = Discipline::where('name_fr', $french)->first();
                if ($d) {
                    // Update name_de only if it's empty to avoid overwriting invalidly?
                    // Or just overwrite. The plan said we persist mapping.
                    // But wait, one french discipline can match multiple german inputs?
                    // No, name_de is a single string. 
                    // Challenge: If 'Weitsprung' -> 'Longueur' and 'Weitsprung Zone' -> 'Longueur'.
                    // 'Longueur' can only have one 'name_de'.
                    // Solution: We should probably have a separate mapping table or JSON column, 
                    // OR simple update 'name_de' if it's empty. If it's used already, we can't easily support multiple aliases.
                    // For now, let's assume one-to-one or ignore if already set.
                    // Actually, the Service uses `findOrMapDiscipline`.
                    // Ideally we should use a simpler approach: Just process normally, and if we can't map one-to-one via DB,
                    // we rely on the session/temporary mapping for this import.
                    
                    // BUT the plan said "Le système sauvegarde ce lien".
                    // Let's modify the plan slightly: update 'name_de' if empty. If not, just use it for this session.
                    
                    if (empty($d->name_de)) {
                        $d->name_de = $german;
                        $d->save();
                    }
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
        // We need the data again, so we'll re-parse the file if needed 
        // OR better: the data was passed during analyzeFile and we don't want to store it in public.
        // Actually, resolveAthletes is called from saveMappings.
        // If we don't store parsedData, we must re-parse.
        $path = $this->csvFile->getRealPath();
        $data = $this->service->parseCsv($path);

        // Pre-calculate status for all rows
        $this->resolvedAthletes = [];
        
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
                  $discipline = $this->service->findOrMapDiscipline($dNameRaw);
             }

             // 3. Resolve Category
             $cNameRaw = $row['raw_category'];
             $cNameMapped = $this->categoryMappings[$cNameRaw] ?? null;
             $category = null;
             if ($cNameMapped) {
                  $category = AthleteCategory::where('name', $cNameMapped)->first();
             } else {
                  $category = $this->service->findOrMapCategory($cNameRaw);
             }
             
             // 4. Check Result Status
             $resultStatus = 'error'; // default
             if ($athlete && $discipline && $category) {
                 $exists = $this->service->checkResultExists($row, $athlete, $discipline, $category);
                 $resultStatus = $exists ? 'duplicate' : 'new';
             }

             $this->resolvedAthletes[$index] = [
                 'row' => $row,
                 'athlete_status' => $isNewAthlete ? 'new' : 'found',
                 'athlete_id' => $athlete?->id,
                 'athlete_name' => $athlete ? ($athlete->first_name . ' ' . $athlete->last_name) : '?',
                 'result_status' => $resultStatus,
                 'discipline_id' => $discipline?->id,
                 'discipline_name' => $discipline?->name_fr,
                 'category_id' => $category?->id,
                 'category_name' => $category?->name,
                 'is_selected' => ($resultStatus === 'new'), // Default select only new results
             ];
        }

        // IMPORTANT: Clear parsedData to free memory and reduce payload size
        // We have everything we need in resolvedAthletes
        // $this->parsedData = []; // Actually leave it empty in properties if we can
    }

    public function executeImport()
    {
        $count = 0;
        $total = count($this->resolvedAthletes);
        
        foreach ($this->resolvedAthletes as $item) {
            if (!$item['is_selected']) continue;
            
            $row = $item['row'];
            
            // Re-resolve athlete with dryRun=false to verify/persist
            [$athlete, $isNew] = $this->service->resolveAthlete($row, false);
            
            // Use IDs from mapping phase if possible to be consistent
            $discipline = $item['discipline_id'] ? Discipline::find($item['discipline_id']) : null;
            $category = $item['category_id'] ? AthleteCategory::find($item['category_id']) : null;

            // Fallback to service mapping if IDs weren't resolved (unlikely if analyzed)
            if (!$discipline) {
                $dNameRaw = $row['raw_discipline'];
                $dNameMapped = $this->disciplineMappings[$dNameRaw] ?? null;
                $discipline = $dNameMapped ? Discipline::where('name_fr', $dNameMapped)->first() : $this->service->findOrMapDiscipline($dNameRaw);
            }

            if (!$category) {
                $cNameRaw = $row['raw_category'];
                $cNameMapped = $this->categoryMappings[$cNameRaw] ?? null;
                $category = $cNameMapped ? AthleteCategory::where('name', $cNameMapped)->first() : $this->service->findOrMapCategory($cNameRaw);
            }
            
            if ($athlete && $discipline && $category) {
                $this->service->importResult($row, $athlete, $discipline, $category);
                $count++;
            }
            
            $this->progress = intval(($count / $total) * 100);
        }
        
        $this->importLogs[] = "Import terminé ! $count résultats traités.";
        $this->step = 4;
    }
    
    public function render()
    {
        return view('livewire.import-historical-data', [
            'availableDisciplines' => Discipline::orderBy('name_fr')->get(),
            'availableCategories' => AthleteCategory::orderBy('name')->get(),
        ])->layout('components.layouts.app');
    }
}
