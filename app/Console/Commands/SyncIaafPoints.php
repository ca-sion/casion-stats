<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncIaafPoints extends Command
{
    protected $signature = 'stats:sync-iaaf-points {--force : Recalculate all even if already set}';

    protected $description = 'Calculate and store IAAF points for all results';

    public function handle()
    {
        $this->info('Starting IAAF points sync...');
        
        $query = \App\Models\Result::query();
        
        if (!$this->option('force')) {
            $query->whereNull('iaaf_points');
        }

        $count = $query->count();
        $this->info("Processing {$count} results...");
        
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $query->with(['discipline', 'athleteCategory'])->chunkById(500, function ($results) use ($bar) {
            foreach ($results as $result) {
                $result->updateQuietly([
                    'iaaf_points' => $result->iaaf_points // This triggers the accessor/calculation
                ]);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('Sync completed!');
    }
}
