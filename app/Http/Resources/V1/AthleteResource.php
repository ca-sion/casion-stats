<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AthleteResource extends JsonResource
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
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'genre' => $this->genre,
            'birth_year' => $this->birthdate?->year,
            'current_category' => CategoryResource::make($this->current_category),
            'activity_period' => [
                'start' => $this->activity_start,
                'end' => $this->activity_end,
            ],
            'personal_bests' => PersonalBestResource::collection(
                $this->when($this->relationLoaded('personalBests'), function () {
                    if (!$this->personalBests) return [];
                    return $this->personalBests->groupBy('discipline_id')->map(function ($disciplineResults) {
                        $discipline = $disciplineResults->first()?->discipline;
                        $descending = strtolower($discipline?->sorting ?? 'asc') === 'desc';
                        return $disciplineResults->sortBy('performance_normalized', SORT_REGULAR, $descending)->first();
                    })->filter()->values();
                })
            ),
            'results' => ResultResource::collection($this->whenLoaded('results')),
        ];
    }
}
