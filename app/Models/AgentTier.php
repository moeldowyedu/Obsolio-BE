<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentTier extends Model
{
    protected $fillable = [
        'name',
        'description',
        'display_order',
    ];

    protected $casts = [
        'display_order' => 'integer',
    ];

    /**
     * Get the agents in this tier.
     */
    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class, 'tier_id');
    }

    /**
     * Get the pricing records for this tier.
     */
    public function pricing(): HasMany
    {
        return $this->hasMany(AgentPricing::class, 'tier_id');
    }
}
