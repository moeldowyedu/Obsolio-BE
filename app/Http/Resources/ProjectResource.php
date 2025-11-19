<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'branch_id' => $this->branch_id,
            'department_id' => $this->department_id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'project_manager_id' => $this->project_manager_id,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'budget' => $this->budget,
            'status' => $this->status,
            'priority' => $this->priority,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Relationships
            'organization' => new OrganizationResource($this->whenLoaded('organization')),
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'project_manager' => new UserResource($this->whenLoaded('projectManager')),
            'teams_count' => $this->whenCounted('teams'),
        ];
    }
}
