<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingCycle extends Model
{
    protected $fillable = [
        'code',
        'name',
        'months',
        'discount_percentage',
    ];

    protected $casts = [
        'months' => 'integer',
        'discount_percentage' => 'decimal:2',
    ];

    /**
     * Get the subscription plans using this billing cycle.
     */
    public function subscriptionPlans(): HasMany
    {
        return $this->hasMany(SubscriptionPlan::class);
    }
}
