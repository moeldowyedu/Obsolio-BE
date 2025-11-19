<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'department_id' => $this->department_id,
            'project_id' => $this->project_id,
            'name' => $this->name,
            'description' => $this->description,
            'team_lead_id' => $this->team_lead_id,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Relationships
            'organization' => new OrganizationResource($this->whenLoaded('organization')),
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'project' => new ProjectResource($this->whenLoaded('project')),
            'team_lead' => new UserResource($this->whenLoaded('teamLead')),
            'members' => UserResource::collection($this->whenLoaded('members')),
            'members_count' => $this->whenCounted('members'),
        ];
    }
}
