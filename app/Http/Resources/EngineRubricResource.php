<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EngineRubricResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'engine_id' => $this->engine_id,
            'name' => $this->name,
            'description' => $this->description,
            'criteria' => $this->criteria,
            'scoring_method' => $this->scoring_method,
            'thresholds' => $this->thresholds,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Relationships
            'engine' => new EngineResource($this->whenLoaded('engine')),
        ];
    }
}
