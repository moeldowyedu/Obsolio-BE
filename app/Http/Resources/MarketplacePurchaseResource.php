<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketplacePurchaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'buyer_tenant_id' => $this->buyer_tenant_id,
            'listing_id' => $this->listing_id,
            'purchased_by_user_id' => $this->purchased_by_user_id,
            'price_paid' => $this->price_paid,
            'payment_method' => $this->payment_method,
            'transaction_id' => $this->transaction_id,
            'license_key' => $this->license_key,
            'status' => $this->status,
            'installed_agent_id' => $this->installed_agent_id,
            'purchased_at' => $this->purchased_at?->toIso8601String(),

            // Relationships
            'listing' => new MarketplaceListingResource($this->whenLoaded('listing')),
            'purchased_by' => new UserResource($this->whenLoaded('purchasedBy')),
            'installed_agent' => new AgentResource($this->whenLoaded('installedAgent')),
        ];
    }
}
