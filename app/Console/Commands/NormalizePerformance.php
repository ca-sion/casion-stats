<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class NormalizePerformance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stats:normalize';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Normalize existing performance strings into numeric values for sorting.';

    use \App\Support\PerformanceNormalizer;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $results = \App\Models\Result::all();
        $this->info("Normalizing {$results->count()} results...");

        $bar = $this->output->createProgressBar($results->count());

        foreach ($results as $result) {
            $result->performance_normalized = $this->parsePerformanceToSeconds($result->performance);
            $result->save();
            
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Normalization complete!');
    }
}
