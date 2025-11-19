<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketplaceListingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'seller_tenant_id' => $this->seller_tenant_id,
            'agent_id' => $this->agent_id,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'tags' => $this->tags,
            'price' => $this->price,
            'pricing_model' => $this->pricing_model,
            'demo_url' => $this->demo_url,
            'documentation_url' => $this->documentation_url,
            'screenshots' => $this->screenshots,
            'version' => $this->version,
            'is_featured' => $this->is_featured,
            'is_active' => $this->is_active,
            'total_purchases' => $this->total_purchases,
            'average_rating' => $this->average_rating,
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Relationships
            'agent' => new AgentResource($this->whenLoaded('agent')),
            'purchases_count' => $this->whenCounted('purchases'),
        ];
    }
}
