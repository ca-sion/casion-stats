<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Result;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EventDeduplicationService
{
    /**
     * Find potential duplicate events.
     * Returns a collection of clusters. Each cluster is an array of Events.
     */
    public function findDuplicates(): Collection
    {
        $events = Event::orderBy('date', 'desc')->get();
        $processedIds = [];
        $clusters = [];

        // Group by exact date to find close matches
        $byDate = $events->groupBy(function ($event) {
            return $event->date->format('Y-m-d');
        });

        foreach ($byDate as $date => $group) {
            if ($group->count() < 2) continue;

            $groupArray = $group->values();
            $count = $groupArray->count();

            for ($i = 0; $i < $count; $i++) {
                $a = $groupArray[$i];
                if (in_array($a->id, $processedIds)) continue;

                $cluster = [$a];
                $hasDuplicates = false;

                for ($j = $i + 1; $j < $count; $j++) {
                    $b = $groupArray[$j];
                    if (in_array($b->id, $processedIds)) continue;

                    if ($this->arePotentialDuplicates($a, $b)) {
                        $cluster[] = $b;
                        $processedIds[] = $b->id;
                        $hasDuplicates = true;
                    }
                }

                if ($hasDuplicates) {
                    $clusters[] = $cluster;
                    $processedIds[] = $a->id;
                }
            }
        }

        return collect($clusters);
    }

    /**
     * Determine if two events are potential duplicates.
     */
    public function arePotentialDuplicates(Event $a, Event $b): bool
    {
        // Must be same date (based on current findDuplicates grouping, but good to be explicit)
        if ($a->date->format('Y-m-d') !== $b->date->format('Y-m-d')) {
            return false;
        }

        $nameA = strtolower($a->name);
        $nameB = strtolower($b->name);

        // 1. Exact Name Match
        if ($nameA === $nameB) {
            return true;
        }

        // 2. Fuzzy Name Match
        $percent = 0;
        similar_text($nameA, $nameB, $percent);
        
        if ($percent > 85) {
            return true;
        }

        // 3. Location awareness if similarity is lower but location matches
        if ($percent > 70 && !empty($a->location) && !empty($b->location)) {
            if (strtolower($a->location) === strtolower($b->location)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Merge secondary event into primary event.
     */
    public function mergeEvents(Event $primary, Event $secondary): bool
    {
        return DB::transaction(function () use ($primary, $secondary) {
            // 1. Reassign Results
            Result::where('event_id', $secondary->id)
                ->update(['event_id' => $primary->id]);

            // 2. Merge Metadata (fill gaps in primary)
            if (empty($primary->location) && !empty($secondary->location)) {
                $primary->location = $secondary->location;
            }
            if (empty($primary->link) && !empty($secondary->link)) {
                $primary->link = $secondary->link;
            }
            if (empty($primary->event_category_id) && !empty($secondary->event_category_id)) {
                $primary->event_category_id = $secondary->event_category_id;
            }

            if ($primary->isDirty()) {
                $primary->save();
            }

            // 3. Delete Secondary
            $secondary->delete();

            return true;
        });
    }
}
