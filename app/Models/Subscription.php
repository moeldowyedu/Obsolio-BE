<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'plan_id',
        'status',
        'billing_cycle',
        'starts_at',
        'ends_at',
        'trial_ends_at',
        'canceled_at',
        'current_period_start',
        'current_period_end',
        'stripe_subscription_id',
        'stripe_customer_id',
        'metadata',
        // Phase 2 fields
        'next_billing_date',
        'auto_renew',
        'cancelled_at',
        'cancellation_reason',
        'execution_quota',
        'executions_used',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'canceled_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        // Phase 2 casts
        'next_billing_date' => 'date',
        'auto_renew' => 'boolean',
        'cancelled_at' => 'datetime',
        'execution_quota' => 'integer',
        'executions_used' => 'integer',
    ];

    /**
     * Get the tenant that owns the subscription.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the plan for this subscription.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    /**
     * Get the invoices for this subscription.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(BillingInvoice::class);
    }

    /**
     * Get the workspace for this subscription.
     */
    public function workspace()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Check if subscription is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if subscription is on trial.
     */
    public function isOnTrial(): bool
    {
        return $this->status === 'trialing' &&
            $this->trial_ends_at &&
            $this->trial_ends_at->isFuture();
    }

    /**
     * Check if subscription is canceled.
     */
    public function isCanceled(): bool
    {
        return $this->status === 'canceled';
    }

    /**
     * Check if subscription is past due.
     */
    public function isPastDue(): bool
    {
        return $this->status === 'past_due';
    }

    /**
     * Check if trial has ended.
     */
    public function hasExpiredTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    /**
     * Get days remaining in trial.
     */
    public function trialDaysRemaining(): int
    {
        if (!$this->trial_ends_at) {
            return 0;
        }

        return max(0, now()->diffInDays($this->trial_ends_at, false));
    }

    /**
     * Check if subscription is monthly.
     */
    public function isMonthly(): bool
    {
        return $this->billing_cycle === 'monthly';
    }

    /**
     * Check if subscription is annual.
     */
    public function isAnnual(): bool
    {
        return $this->billing_cycle === 'annual';
    }

    // ========================================
    // PHASE 2 METHODS - Usage Tracking
    // ========================================

    /**
     * Check if execution quota is exceeded
     */
    public function hasExceededQuota()
    {
        return $this->executions_used >= $this->execution_quota;
    }

    /**
     * Get remaining executions
     */
    public function getRemainingExecutions()
    {
        return max(0, $this->execution_quota - $this->executions_used);
    }

    /**
     * Get usage percentage
     */
    public function getUsagePercentage()
    {
        if ($this->execution_quota == 0) {
            return 0;
        }

        return min(100, round(($this->executions_used / $this->execution_quota) * 100, 2));
    }

    /**
     * Increment execution count
     */
    public function incrementExecutions($count = 1)
    {
        $this->increment('executions_used', $count);

        return $this->executions_used;
    }

    /**
     * Calculate overage executions
     */
    public function getOverageExecutions()
    {
        return max(0, $this->executions_used - $this->execution_quota);
    }

    /**
     * Calculate overage cost
     */
    public function calculateOverageCost()
    {
        if (!$this->plan || !$this->plan->overage_price_per_execution) {
            return 0;
        }

        $overage = $this->getOverageExecutions();
        return $overage * $this->plan->overage_price_per_execution;
    }

    // ========================================
    // PHASE 2 METHODS - Billing Cycle
    // ========================================

    /**
     * Reset monthly usage (called at billing cycle)
     */
    public function resetMonthlyUsage()
    {
        $billingCycle = $this->plan->billingCycle;
        $months = $billingCycle ? $billingCycle->months : 1;

        $this->update([
            'executions_used' => 0,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonths($months),
            'next_billing_date' => now()->addMonths($months),
        ]);
    }

    /**
     * Check if subscription is due for renewal
     */
    public function isDueForRenewal()
    {
        return $this->status === 'active' &&
            $this->auto_renew &&
            $this->next_billing_date &&
            $this->next_billing_date->lte(now());
    }

    /**
     * Cancel subscription
     */
    public function cancel($reason = null, $immediately = false)
    {
        if ($immediately) {
            $this->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'auto_renew' => false,
            ]);
        } else {
            // Cancel at end of period
            $this->update([
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'auto_renew' => false,
            ]);
        }
    }

    /**
     * Reactivate subscription
     */
    public function reactivate()
    {
        $this->update([
            'status' => 'active',
            'cancelled_at' => null,
            'cancellation_reason' => null,
            'auto_renew' => true,
        ]);
    }

    // ========================================
    // PHASE 2 SCOPES
    // ========================================

    /**
     * Scope to get subscriptions due for renewal
     */
    public function scopeDueForRenewal($query)
    {
        return $query->where('status', 'active')
            ->where('auto_renew', true)
            ->whereDate('next_billing_date', '<=', now());
    }

    /**
     * Scope to get subscriptions expiring soon
     */
    public function scopeExpiringSoon($query, $days = 7)
    {
        return $query->where('status', 'active')
            ->whereDate('current_period_end', '<=', now()->addDays($days))
            ->whereDate('current_period_end', '>=', now());
    }

    /**
     * Scope to get cancelled subscriptions
     */
    public function scopeCancelled($query)
    {
        return $query->whereNotNull('cancelled_at');
    }
}