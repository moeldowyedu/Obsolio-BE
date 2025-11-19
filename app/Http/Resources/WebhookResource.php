<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebhookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'url' => $this->url,
            'events' => $this->events,
            'secret' => $this->when($request->user()?->id === $this->user_id, $this->secret),
            'headers' => $this->headers,
            'is_active' => $this->is_active,
            'last_triggered_at' => $this->last_triggered_at?->toIso8601String(),
            'total_calls' => $this->total_calls,
            'failed_calls' => $this->failed_calls,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Relationships
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
