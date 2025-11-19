<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HITLApprovalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'job_flow_id' => $this->job_flow_id,
            'agent_id' => $this->agent_id,
            'execution_id' => $this->execution_id,
            'task_data' => $this->task_data,
            'ai_decision' => $this->ai_decision,
            'ai_confidence' => $this->ai_confidence,
            'rubric_score' => $this->rubric_score,
            'status' => $this->status,
            'assigned_to_user_id' => $this->assigned_to_user_id,
            'reviewed_by_user_id' => $this->reviewed_by_user_id,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'reviewer_comments' => $this->reviewer_comments,
            'priority' => $this->priority,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),

            // Relationships
            'job_flow' => new JobFlowResource($this->whenLoaded('jobFlow')),
            'agent' => new AgentResource($this->whenLoaded('agent')),
            'execution' => new AgentExecutionResource($this->whenLoaded('execution')),
            'assigned_to' => new UserResource($this->whenLoaded('assignedTo')),
            'reviewed_by' => new UserResource($this->whenLoaded('reviewedBy')),
        ];
    }
}
