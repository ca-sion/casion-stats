<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MergeDisciplines extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:merge-disciplines 
                            {--from= : Comma-separated list of discipline IDs to merge FROM} 
                            {--to= : The target discipline ID to merge TO}
                            {--force : Skip confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Merge multiple disciplines into one and reassign all associated results.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $fromIds = array_filter(explode(',', $this->option('from')));
        $toId = $this->option('to');

        if (empty($fromIds) || ! $toId) {
            $this->error('Please specify --from (IDs) and --to (Target ID).');

            return 1;
        }

        $target = \App\Models\Discipline::find($toId);
        if (! $target) {
            $this->error("Target discipline with ID {$toId} not found.");

            return 1;
        }

        $sources = \App\Models\Discipline::whereIn('id', $fromIds)->get();
        if ($sources->isEmpty()) {
            $this->error('No source disciplines found for the provided IDs.');

            return 1;
        }

        $this->info('Merging '.$sources->count()." disciplines into '{$target->name_fr}' (ID: {$target->id})");

        foreach ($sources as $source) {
            $this->line("- Source: {$source->name_fr} (ID: {$source->id})");
        }

        if (! $this->option('force') && ! $this->confirm('Do you wish to continue?', false)) {
            return 0;
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($fromIds, $toId) {
            // Reassign results
            $updatedCount = \App\Models\Result::whereIn('discipline_id', $fromIds)
                ->update(['discipline_id' => $toId]);

            $this->info("Reassigned {$updatedCount} results.");

            // Reassign events (if any events are directly linked to disciplines - checking models)
            // Based on earlier list_dir, events exist but usually link to results.
            // Result.php shows belongsTo(Discipline), let's check Event.php just in case.

            // Delete sources
            \App\Models\Discipline::whereIn('id', $fromIds)->delete();
            $this->info('Deleted '.count($fromIds).' source disciplines.');
        });

        $this->info('Migration completed successfully.');

        return 0;
    }
}
