<?php

namespace App\Observers;

use App\Models\Result;

class ResultObserver
{
    /**
     * Handle the Result "saving" event.
     */
    public function saving(Result $result): void
    {
        // 1. Normalize performance if it has changed
        if ($result->isDirty('performance') || $result->performance_normalized === null) {
            $result->performance_normalized = $result->parsePerformanceToSeconds($result->performance);
        }

        // 2. Calculate IAAF points if performance or discipline (or category/gender) changed
        // We check isDirty for fields affecting points or if points are missing.
        $affectsPoints = ['performance_normalized', 'discipline_id', 'athlete_category_id'];
        
        if ($result->isDirty($affectsPoints) || $result->iaaf_points === null || $result->iaaf_points == 0) {
            $result->iaaf_points = $result->iaaf_points_calculated;
        }
    }
}
