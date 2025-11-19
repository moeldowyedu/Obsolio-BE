<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobFlowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'agent_id' => $this->agent_id,
            'job_title' => $this->job_title,
            'job_description' => $this->job_description,
            'organization_id' => $this->organization_id,
            'branch_id' => $this->branch_id,
            'department_id' => $this->department_id,
            'project_id' => $this->project_id,
            'reporting_manager_id' => $this->reporting_manager_id,
            'employment_type' => $this->employment_type,
            'schedule_type' => $this->schedule_type,
            'schedule_config' => $this->schedule_config,
            'input_source' => $this->input_source,
            'output_destination' => $this->output_destination,
            'hitl_mode' => $this->hitl_mode,
            'hitl_supervisor_id' => $this->hitl_supervisor_id,
            'hitl_rules' => $this->hitl_rules,
            'status' => $this->status,
            'last_run_at' => $this->last_run_at?->toIso8601String(),
            'next_run_at' => $this->next_run_at?->toIso8601String(),
            'total_runs' => $this->total_runs,
            'successful_runs' => $this->successful_runs,
            'failed_runs' => $this->failed_runs,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Relationships
            'agent' => new AgentResource($this->whenLoaded('agent')),
            'organization' => new OrganizationResource($this->whenLoaded('organization')),
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'project' => new ProjectResource($this->whenLoaded('project')),
            'reporting_manager' => new UserResource($this->whenLoaded('reportingManager')),
            'hitl_supervisor' => new UserResource($this->whenLoaded('hitlSupervisor')),
        ];
    }
}
