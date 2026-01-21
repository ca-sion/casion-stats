<?php

namespace App\Traits;

use App\Services\IaafPointsService;
use Illuminate\Database\Eloquent\Casts\Attribute;

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
