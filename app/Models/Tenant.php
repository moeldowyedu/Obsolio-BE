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
        'plan_id',
        'organization_id',
        'name',
        'email',
        'phone',
        'type',
        'status',
        'trial_ends_at',
        'subdomain_preference',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
    ];

    /**
     * Get the subscription plan.
     */
    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    /**
     * Get the organizations for this tenant.
     */
    public function organizations()
    {
        return $this->hasMany(Organization::class);
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
     */
    public function isOnTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
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
}