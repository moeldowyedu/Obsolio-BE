<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'name',
        'short_name',
        'type',
        'status',
        'trial_ends_at',
        'plan_id',
        'billing_cycle',
        'subdomain_preference',
        'subdomain_activated_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'trial_ends_at' => 'datetime',
    ];

    /**
     * Get the organizations for the tenant.
     */
    public function organizations()
    {
        return $this->hasMany(Organization::class);
    }

    /**
     * Get the users for the tenant.
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the memberships for the tenant.
     */
    public function memberships()
    {
        return $this->hasMany(TenantMembership::class, 'tenant_id');
    }

    /**
     * Get the owner membership for the tenant.
     */
    public function ownerMembership()
    {
        return $this->hasOne(TenantMembership::class, 'tenant_id')
            ->where('role', TenantMembership::ROLE_OWNER);
    }

    /**
     * Get the agents for the tenant.
     */
    public function agents()
    {
        return $this->hasMany(Agent::class);
    }

    /**
     * Get the subscriptions for the tenant.
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
        return $this->hasOne(Subscription::class)
            ->where('status', 'active')
            ->latest();
    }

    /**
     * Check if tenant is on trial.
     */
    public function isOnTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Check if tenant trial has expired.
     */
    public function hasExpiredTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }
}
