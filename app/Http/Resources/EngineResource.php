<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EngineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'provider' => $this->provider,
            'model_name' => $this->model_name,
            'version' => $this->version,
            'description' => $this->description,
            'capabilities' => $this->capabilities,
            'input_types' => $this->input_types,
            'output_types' => $this->output_types,
            'pricing' => $this->pricing,
            'limits' => $this->limits,
            'config' => $this->config,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Relationships
            'rubrics' => EngineRubricResource::collection($this->whenLoaded('rubrics')),
        ];
    }
}
