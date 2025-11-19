<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'created_by_user_id' => $this->created_by_user_id,
            'name' => $this->name,
            'description' => $this->description,
            'workflow_definition' => $this->workflow_definition,
            'input_schema' => $this->input_schema,
            'output_schema' => $this->output_schema,
            'version' => $this->version,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Relationships
            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'executions_count' => $this->whenCounted('executions'),
        ];
    }
}
