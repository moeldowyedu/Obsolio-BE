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
        'email',
        'phone',
        'type',
        'status',
        'subdomain_preference',
        'subdomain_activated_at',
    ];

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
     * Get the organization this tenant belongs to.
     * (For organization-type tenants only, personal tenants return null)
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id');
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
}