<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    protected $fillable = [
        'id',
        'organization_id',
        'name',
        'short_name',
        'email',
        'phone',
        'type',
        'status',
        'subdomain_preference',
        'subdomain_activated_at',
    ];

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'organization_id',
            'name',
            'short_name',
            'type',
            'status',
            'subdomain_preference',
            'subdomain_activated_at',
            'domain',
        ];
    }

    /**
     * Get the owner membership.
     */
    public function ownerMembership()
    {
        return $this->hasOne(TenantMembership::class)->where('role', 'owner');
    }

    protected $casts = [
        'subdomain_activated_at' => 'datetime',
    ];

    /**
     * Get the organization this tenant belongs to / owns.
     * (Enforcing 1-to-1 relationship)
     */
    public function organization()
    {
        return $this->hasOne(Organization::class);
    }

    /**
     * Get all organizations for this tenant.
     * (Legacy relationship - a tenant can have multiple organizations)
     */
    public function organizations()
    {
        return $this->hasMany(Organization::class);
    }

    /**
     * Get the current subscription plan through active subscription.
     */
    public function currentPlan()
    {
        return $this->hasOneThrough(
            SubscriptionPlan::class,
            Subscription::class,
            'tenant_id',
            'id',
            'id',
            'plan_id'
        )->whereIn('subscriptions.status', ['trialing', 'active']);
    }

    /**
     * Get tenant memberships.
     */
    public function memberships()
    {
        return $this->hasMany(TenantMembership::class);
    }

    /**
     * Get all subscriptions.
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get the active subscription for the tenant.
     */
    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class)->whereIn('status', ['trialing', 'active']);
    }

    /**
     * Get tenant agents.
     */
    public function agents()
    {
        return $this->belongsToMany(Agent::class, 'tenant_agents')
            ->withPivot([
                'status',
                'purchased_at',
                'activated_at',
                'expires_at',
                'last_used_at',
                'usage_count',
                'configuration',
                'metadata'
            ])
            ->withTimestamps();
    }

    /**
     * Get tenant invoices.
     */
    public function invoices()
    {
        return $this->hasMany(BillingInvoice::class);
    }

    /**
     * Get payment methods.
     */
    public function paymentMethods()
    {
        return $this->hasMany(PaymentMethod::class);
    }

    /**
     * Check if tenant is on trial.
     * Now checks the active subscription instead of tenant directly.
     */
    public function isOnTrial(): bool
    {
        $subscription = $this->activeSubscription;
        return $subscription && $subscription->isOnTrial();
    }

    /**
     * Get days remaining in trial.
     * Now delegates to active subscription.
     */
    public function trialDaysRemaining(): int
    {
        $subscription = $this->activeSubscription;
        return $subscription ? $subscription->trialDaysRemaining() : 0;
    }

    /**
     * Check if tenant has an active subscription.
     */
    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscription()->exists();
    }

    /**
     * Get the current billing cycle.
     */
    public function billingCycle(): ?string
    {
        $subscription = $this->activeSubscription;
        return $subscription?->billing_cycle;
    }

    /**
     * Check if tenant is a personal account.
     */
    public function isPersonal(): bool
    {
        return $this->type === 'personal';
    }

    /**
     * Check if tenant is an organization account.
     */
    public function isOrganization(): bool
    {
        return $this->type === 'organization';
    }

    // ========================================
    // PHASE 3 RELATIONSHIPS & METHODS
    // ========================================

    /**
     * Agent subscriptions (add-ons)
     */
    public function agentSubscriptions()
    {
        return $this->hasMany(AgentSubscription::class);
    }

    /**
     * Active agent subscriptions
     */
    public function activeAgentSubscriptions()
    {
        return $this->agentSubscriptions()->active();
    }

    /**
     * Usage tracking records
     */
    public function usageTracking()
    {
        return $this->hasMany(AgentUsageTracking::class);
    }

    /**
     * Get current month usage count
     */
    public function getCurrentMonthExecutions()
    {
        return $this->usageTracking()
            ->currentMonth()
            ->count();
    }

    /**
     * Get subscribed agents (from add-ons)
     */
    public function subscribedAgents()
    {
        return $this->belongsToMany(Agent::class, 'agent_subscriptions')
            ->wherePivot('status', 'active')
            ->withPivot(['monthly_price', 'current_period_start', 'current_period_end']);
    }

    /**
     * Check if tenant has specific agent subscription
     */
    public function hasAgentSubscription($agentId)
    {
        return $this->agentSubscriptions()
            ->where('agent_id', $agentId)
            ->active()
            ->exists();
    }

    /**
     * Get total monthly agent subscription cost
     */
    public function getMonthlyAgentCost()
    {
        return $this->activeAgentSubscriptions()
            ->sum('monthly_price');
    }

    // ========================================
    // PHASE 4 RELATIONSHIPS & METHODS
    // ========================================
    /**
     * Pending invoices
     */
    public function pendingInvoices()
    {
        return $this->invoices()->pending();
    }

    /**
     * Overdue invoices
     */
    public function overdueInvoices()
    {
        return $this->invoices()->overdue();
    }

    /**
     * Get total outstanding balance
     */
    public function getOutstandingBalance()
    {
        return $this->pendingInvoices()->sum('total_amount');
    }

    /**
     * Get most recent invoice
     */
    public function getLatestInvoice()
    {
        return $this->invoices()
            ->latest()
            ->first();
    }
}