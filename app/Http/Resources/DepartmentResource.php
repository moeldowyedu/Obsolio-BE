<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'branch_id' => $this->branch_id,
            'parent_department_id' => $this->parent_department_id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'head_id' => $this->head_id,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Relationships
            'organization' => new OrganizationResource($this->whenLoaded('organization')),
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'head' => new UserResource($this->whenLoaded('head')),
            'parent_department' => new DepartmentResource($this->whenLoaded('parentDepartment')),
            'sub_departments' => DepartmentResource::collection($this->whenLoaded('subDepartments')),
        ];
    }
}
