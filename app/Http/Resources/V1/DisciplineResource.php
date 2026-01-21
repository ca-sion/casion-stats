<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DisciplineResource extends JsonResource
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
            'name' => $this->name_fr,
            'name_fr' => $this->name_fr,
            'name_de' => $this->name_de,
            'name_en' => $this->name_en,
            'code' => $this->code,
            'wa_code' => $this->wa_code,
            'seltec_code' => $this->seltec_code,
            'has_wind' => $this->has_wind,
            'type' => $this->type,
            'is_relay' => $this->is_relay,
        ];
    }
}
