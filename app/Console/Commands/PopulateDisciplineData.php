<?php

namespace App\Console\Commands;

use App\Models\Discipline;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PopulateDisciplineData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:populate-discipline-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Populate disciplines table with data from wa_disciplines.json';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $path = resource_path('data/wa_disciplines.json');

        if (! File::exists($path)) {
            $this->error("File not found at: {$path}");

            return 1;
        }

        $json = File::get($path);
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Invalid JSON format: '.json_last_error_msg());

            return 1;
        }

        $this->info('Starting population of '.count($data).' disciplines...');

        $updatedCount = 0;
        $notFoundCount = 0;

        // Pre-fetch all disciplines to avoid high query count
        $disciplines = Discipline::all();

        foreach ($data as $entry) {
            $lanetId = $entry['lanet_id'] ?? null;
            $jsonName = $entry['name'] ?? null;
            $normalizedJsonName = $this->normalize($jsonName ?? '');

            if (! $normalizedJsonName) {
                continue;
            }

            // 1. Try strategy: Find by seltec_id (lanet_id)
            $discipline = null;
            if ($lanetId) {
                $discipline = $disciplines->firstWhere('seltec_id', $lanetId);
            }

            // 2. Try strategy: Exact match on normalized names (FR, DE, EN)
            if (! $discipline) {
                $discipline = $disciplines->first(function ($d) use ($normalizedJsonName) {
                    return $this->normalize($d->name_fr ?? '') === $normalizedJsonName ||
                           $this->normalize($d->name_de ?? '') === $normalizedJsonName ||
                           $this->normalize($d->name_en ?? '') === $normalizedJsonName;
                });
            }

            // 3. Try strategy: JSON name is part of DB name (e.g., "Suédois" vs "Relais suédois")
            if (! $discipline) {
                $discipline = $disciplines->first(function ($d) use ($normalizedJsonName) {
                    $normFr = $this->normalize($d->name_fr ?? '');

                    return str_contains($normFr, $normalizedJsonName);
                });
            }

            if ($discipline) {
                $discipline->update([
                    'name_en' => $entry['wa_discipline'] ?? $discipline->name_en,
                    'wa_code' => $entry['wa_discipline_code'] ?? $discipline->wa_code,
                    'seltec_id' => $lanetId ?? $discipline->seltec_id,
                    'seltec_code' => $entry['seltec_code'] ?? $discipline->seltec_code,
                    'code' => $entry['code'] ?? $discipline->code,
                    'has_wind' => (bool) ($entry['wind'] ?? $discipline->has_wind),
                ]);
                $updatedCount++;
            } else {
                $notFoundCount++;
                $this->warn("Skipped: {$jsonName}");
            }
        }

        $this->info('Completed!');
        $this->info("Updated: {$updatedCount}");
        $this->info("Not found in database: {$notFoundCount}");

        return 0;
    }

    private function normalize(string $string): string
    {
        $string = strtolower($string);
        // Replace common abbreviations/terms
        $string = str_replace(['mètres', 'meter', 'mètre'], 'm', $string);
        $string = str_replace('walk', 'marche', $string);
        $string = str_replace(' ', '', $string);

        // Remove accents
        if (function_exists('iconv')) {
            $string = @iconv('UTF-8', 'ASCII//TRANSLIT', $string);
        }

        // Remove non-alphanumeric
        $string = preg_replace('/[^a-z0-9]/', '', (string) $string);

        return $string;
    }
}
