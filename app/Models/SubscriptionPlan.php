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
        'limits',
        'max_users',
        'max_agents',
        'storage_gb',
        'is_active',
        'trial_days',
        'description',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'features' => 'array',
        'limits' => 'array',
        'is_active' => 'boolean',
        'price_monthly' => 'decimal:2',
        'price_annual' => 'decimal:2',
    ];

    /**
     * Get the subscriptions for this plan.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }

    /**
     * Get the tenants using this plan.
     */
    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class, 'plan_id');
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
}