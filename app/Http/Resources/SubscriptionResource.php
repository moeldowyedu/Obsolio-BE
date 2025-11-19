<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'plan_name' => $this->plan_name,
            'billing_cycle' => $this->billing_cycle,
            'price' => $this->price,
            'status' => $this->status,
            'current_period_start' => $this->current_period_start,
            'current_period_end' => $this->current_period_end,
            'cancel_at_period_end' => $this->cancel_at_period_end,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'auto_renew' => $this->auto_renew,
            'payment_method' => $this->payment_method,
            'last_payment_at' => $this->last_payment_at?->toIso8601String(),
            'next_billing_at' => $this->next_billing_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
