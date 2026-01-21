<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonalBestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Result Identification
            'result_id' => $this->id,

            // Performance
            'performance' => $this->performance,
            'performance_normalized' => $this->performance_normalized,
            'wind' => $this->wind, // Standard field if available

            // Discipline Details (Flattened)
            'discipline_id' => $this->discipline?->id,
            'discipline_name' => $this->discipline?->name_fr,
            'discipline_name_fr' => $this->discipline?->name_fr,
            'discipline_name_de' => $this->discipline?->name_de,
            'discipline_name_en' => $this->discipline?->name_en,
            'discipline_code' => $this->discipline?->code,
            'discipline_order' => $this->discipline?->order,

            // Event Details (Flattened)
            'event_id' => $this->event?->id,
            'event_name' => $this->event?->name,
            'location' => $this->event?->location,
            'date' => $this->event?->date,
            'year' => $this->event?->date?->year,

            // Category Details (at the time of performance)
            'category_id' => $this->athleteCategory?->id,
            'category_name' => $this->athleteCategory?->name,
        ];
    }
}
