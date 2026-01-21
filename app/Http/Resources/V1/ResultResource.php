<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResultResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'performance' => $this->performance,
            'performance_normalized' => $this->performance_normalized,
            'year' => $this->event?->date?->year,
            'date' => $this->event?->date,
            'location' => $this->event?->location,
            'event_name' => $this->event?->name,
            'discipline' => DisciplineResource::make($this->whenLoaded('discipline')),
            'category' => CategoryResource::make($this->whenLoaded('athleteCategory')),
            'event' => JsonResource::make($this->whenLoaded('event')),
        ];
    }
}
