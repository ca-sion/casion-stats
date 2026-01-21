<?php

namespace App\Traits;

use App\Services\IaafPointsService;

trait HasIaafPoints
{
    /**
     * Get the IAAF points for this result.
     */
    public function getIaafPointsAttribute(): int
    {
        return app(IaafPointsService::class)->getPoints($this);
    }
}
