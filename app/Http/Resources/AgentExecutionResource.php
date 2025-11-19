<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgentExecutionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'agent_id' => $this->agent_id,
            'job_flow_id' => $this->job_flow_id,
            'workflow_execution_id' => $this->workflow_execution_id,
            'triggered_by_user_id' => $this->triggered_by_user_id,
            'input_data' => $this->input_data,
            'output_data' => $this->output_data,
            'status' => $this->status,
            'error_message' => $this->error_message,
            'execution_time_ms' => $this->execution_time_ms,
            'tokens_used' => $this->tokens_used,
            'cost' => $this->cost,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),

            // Relationships
            'agent' => new AgentResource($this->whenLoaded('agent')),
            'job_flow' => new JobFlowResource($this->whenLoaded('jobFlow')),
            'workflow_execution' => new WorkflowExecutionResource($this->whenLoaded('workflowExecution')),
            'triggered_by' => new UserResource($this->whenLoaded('triggeredBy')),
        ];
    }
}
