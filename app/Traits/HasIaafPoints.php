<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Services\IaafPointsService;

trait HasIaafPoints
{
    /**
     * Get the IAAF points for this result.
     */
    protected function iaafPointsCalculated(): Attribute
    {
        return Attribute::make(
            get: fn () => app(IaafPointsService::class)->getPoints($this),
        );
    }
}
