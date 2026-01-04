<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'type',
        'tier',
        'price_monthly',
        'price_annual',
        'features',
        'highlight_features',
        'limits',
        'max_users',
        'max_agents',
        'storage_gb',
        'is_active',
        'is_published',
        'is_archived',
        'plan_version',
        'parent_plan_id',
        'display_order',
        'trial_days',
        'description',
        'metadata',
        // Phase 2 fields
        'billing_cycle_id',
        'base_price',
        'final_price',
        'included_executions',
        'overage_price_per_execution',
        'max_agent_slots',
        'max_basic_agents',
        'max_professional_agents',
        'max_specialized_agents',
        'max_enterprise_agents',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'features' => 'array',
        'highlight_features' => 'array',
        'limits' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'is_published' => 'boolean',
        'is_archived' => 'boolean',
        'price_monthly' => 'decimal:2',
        'price_annual' => 'decimal:2',
        // Phase 2 casts
        'base_price' => 'decimal:2',
        'final_price' => 'decimal:2',
        'overage_price_per_execution' => 'decimal:4',
        'included_executions' => 'integer',
        'max_agent_slots' => 'integer',
        'max_basic_agents' => 'integer',
        'max_professional_agents' => 'integer',
        'max_specialized_agents' => 'integer',
        'max_enterprise_agents' => 'integer',
    ];

    /**
     * Get the subscriptions for this plan.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }

    /**
     * Get the billing cycle for this plan.
     */
    public function billingCycle()
    {
        return $this->belongsTo(BillingCycle::class);
    }

    /**
     * Get the parent plan (for versioned plans).
     */
    public function parentPlan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'parent_plan_id');
    }

    /**
     * Get child plans (plan versions).
     */
    public function childPlans()
    {
        return $this->hasMany(SubscriptionPlan::class, 'parent_plan_id');
    }

    /**
     * Check if plan is free tier.
     */
    public function isFree(): bool
    {
        return $this->tier === 'free';
    }

    /**
     * Check if plan is for personal use.
     */
    public function isPersonal(): bool
    {
        return $this->type === 'personal';
    }

    /**
     * Check if plan is for organizations.
     */
    public function isOrganization(): bool
    {
        return $this->type === 'organization';
    }

    /**
     * Get monthly price or 0 if free.
     */
    public function getMonthlyPriceAttribute($value)
    {
        return $value ?? 0;
    }

    /**
     * Get annual price or 0 if free.
     */
    public function getAnnualPriceAttribute($value)
    {
        return $value ?? 0;
    }

    /**
     * Calculate annual savings percentage.
     */
    public function getAnnualSavingsPercentage(): int
    {
        if (!$this->price_monthly || !$this->price_annual) {
            return 0;
        }

        $monthlyYearly = $this->price_monthly * 12;
        $savings = $monthlyYearly - $this->price_annual;

        return (int) round(($savings / $monthlyYearly) * 100);
    }

    /**
     * Check if plan is published and available for selection.
     */
    public function isPublished(): bool
    {
        return $this->is_published && $this->is_active && !$this->is_archived;
    }

    /**
     * Check if plan is archived.
     */
    public function isArchived(): bool
    {
        return $this->is_archived;
    }

    /**
     * Scope to get only published plans.
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true)
            ->where('is_active', true)
            ->where('is_archived', false);
    }

    /**
     * Scope to get plans by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get active plans.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('is_archived', false);
    }

    /**
     * Get the display name with tier.
     */
    public function getDisplayName(): string
    {
        return "{$this->name} ({$this->tier})";
    }

    /**
     * Count active subscriptions for this plan.
     */
    public function activeSubscriptionsCount(): int
    {
        return $this->subscriptions()
            ->whereIn('status', ['trialing', 'active'])
            ->count();
    }

    // ========================================
    // PHASE 2 METHODS
    // ========================================

    /**
     * Check if plan allows adding an agent of specific tier
     */
    public function allowsAgentTier($tierId, $currentCount = 0)
    {
        switch ($tierId) {
            case 1: // Basic
                return $this->max_basic_agents === null ||
                    $this->max_basic_agents === 999 ||
                    $currentCount < $this->max_basic_agents;

            case 2: // Professional
                return $this->max_professional_agents === null ||
                    $this->max_professional_agents === 999 ||
                    $currentCount < $this->max_professional_agents;

            case 3: // Specialized
                return $this->max_specialized_agents === null ||
                    $this->max_specialized_agents === 999 ||
                    $currentCount < $this->max_specialized_agents;

            case 4: // Enterprise
                return $this->max_enterprise_agents === null ||
                    $this->max_enterprise_agents === 999 ||
                    $currentCount < $this->max_enterprise_agents;

            default:
                return false;
        }
    }

    /**
     * Get monthly price (useful for annual/semi-annual plans)
     */
    public function getMonthlyEquivalentPrice()
    {
        if (!$this->billingCycle) {
            return $this->final_price;
        }

        return round($this->final_price / $this->billingCycle->months, 2);
    }

    /**
     * Get discount amount
     */
    public function getDiscountAmountAttribute()
    {
        return $this->base_price - $this->final_price;
    }

    /**
     * Check if plan has execution overage pricing
     */
    public function hasOveragePricing()
    {
        return $this->overage_price_per_execution !== null;
    }

    /**
     * Get remaining agent slots by tier
     */
    public function getRemainingSlots($currentAgents)
    {
        return [
            'basic' => $this->max_basic_agents === 999 ? 'unlimited' :
                max(0, $this->max_basic_agents - ($currentAgents['basic'] ?? 0)),

            'professional' => $this->max_professional_agents === 999 ? 'unlimited' :
                max(0, $this->max_professional_agents - ($currentAgents['professional'] ?? 0)),

            'specialized' => $this->max_specialized_agents === 999 ? 'unlimited' :
                max(0, $this->max_specialized_agents - ($currentAgents['specialized'] ?? 0)),

            'enterprise' => $this->max_enterprise_agents === 999 ? 'unlimited' :
                max(0, $this->max_enterprise_agents - ($currentAgents['enterprise'] ?? 0)),
        ];
    }

    /**
     * Scope to get plans for a specific billing cycle
     */
    public function scopeForBillingCycle($query, $cycleId)
    {
        return $query->where('billing_cycle_id', $cycleId);
    }
}