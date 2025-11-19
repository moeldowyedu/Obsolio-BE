<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UsageRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'subscription_id' => $this->subscription_id,
            'resource_type' => $this->resource_type,
            'resource_id' => $this->resource_id,
            'metric_name' => $this->metric_name,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'cost' => $this->cost,
            'metadata' => $this->metadata,
            'recorded_at' => $this->recorded_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),

            // Relationships
            'subscription' => new SubscriptionResource($this->whenLoaded('subscription')),
        ];
    }
}
