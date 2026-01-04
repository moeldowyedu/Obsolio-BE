<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentPricing extends Model
{
    protected $table = 'agent_pricing';

    protected $fillable = [
        'agent_id',
        'tier_id',
        'monthly_price',
        'price_per_task',
        'included_tasks_per_month',
        'is_active',
        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'monthly_price' => 'decimal:2',
        'price_per_task' => 'decimal:4',
        'included_tasks_per_month' => 'integer',
        'is_active' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    /**
     * Get the agent this pricing belongs to.
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * Get the tier this pricing belongs to.
     */
    public function tier(): BelongsTo
    {
        return $this->belongsTo(AgentTier::class);
    }

    /**
     * Scope: Active pricing only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', now());
            });
    }
}
