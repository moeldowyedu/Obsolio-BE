<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'created_by_user_id' => $this->created_by_user_id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'engines_used' => $this->engines_used,
            'config' => $this->config,
            'input_schema' => $this->input_schema,
            'output_schema' => $this->output_schema,
            'rubric_config' => $this->rubric_config,
            'status' => $this->status,
            'is_published' => $this->is_published,
            'marketplace_listing_id' => $this->marketplace_listing_id,
            'version' => $this->version,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Relationships
            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'marketplace_listing' => new MarketplaceListingResource($this->whenLoaded('marketplaceListing')),
            'job_flows_count' => $this->whenCounted('jobFlows'),
            'executions_count' => $this->whenCounted('executions'),
        ];
    }
}
