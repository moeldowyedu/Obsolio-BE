<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'organization_id' => $this->organization_id,
            'branch_id' => $this->branch_id,
            'department_id' => $this->department_id,
            'project_id' => $this->project_id,
            'assignment_type' => $this->assignment_type,
            'role' => $this->role,
            'started_at' => $this->started_at,
            'ended_at' => $this->ended_at,
            'is_primary' => $this->is_primary,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Relationships
            'user' => new UserResource($this->whenLoaded('user')),
            'organization' => new OrganizationResource($this->whenLoaded('organization')),
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'project' => new ProjectResource($this->whenLoaded('project')),
        ];
    }
}
