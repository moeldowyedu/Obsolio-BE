<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowExecutionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'workflow_id' => $this->workflow_id,
            'triggered_by_user_id' => $this->triggered_by_user_id,
            'input_data' => $this->input_data,
            'output_data' => $this->output_data,
            'status' => $this->status,
            'current_step' => $this->current_step,
            'execution_log' => $this->execution_log,
            'error_message' => $this->error_message,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),

            // Relationships
            'workflow' => new WorkflowResource($this->whenLoaded('workflow')),
            'triggered_by' => new UserResource($this->whenLoaded('triggeredBy')),
        ];
    }
}
