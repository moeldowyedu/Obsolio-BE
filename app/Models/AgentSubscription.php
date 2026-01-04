<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AgentSubscription extends Model
{
    protected $fillable = [
        'tenant_id',
        'agent_id',
        'monthly_price',
        'status',
        'started_at',
        'current_period_start',
        'current_period_end',
        'next_billing_date',
        'cancelled_at',
        'auto_renew',
    ];

    protected $casts = [
        'monthly_price' => 'decimal:2',
        'started_at' => 'datetime',
        'current_period_start' => 'date',
        'current_period_end' => 'date',
        'next_billing_date' => 'date',
        'cancelled_at' => 'datetime',
        'auto_renew' => 'boolean',
    ];

    // RELATIONSHIPS

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function usageTracking()
    {
        return $this->hasMany(AgentUsageTracking::class, 'agent_id', 'agent_id')
            ->where('tenant_id', $this->tenant_id);
    }

    // SCOPES

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeDueForRenewal($query)
    {
        return $query->where('status', 'active')
            ->where('auto_renew', true)
            ->whereDate('next_billing_date', '<=', now());
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeExpiringSoon($query, $days = 7)
    {
        return $query->where('status', 'active')
            ->whereDate('next_billing_date', '<=', now()->addDays($days))
            ->whereDate('next_billing_date', '>=', now());
    }

    // METHODS

    /**
     * Cancel agent subscription
     */
    public function cancel($reason = null)
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'auto_renew' => false,
        ]);

        // Optionally deactivate in tenant_agents
        DB::table('tenant_agents')
            ->where('tenant_id', $this->tenant_id)
            ->where('agent_id', $this->agent_id)
            ->update([
                'is_active' => false,
                'deactivated_at' => now(),
            ]);
    }

    /**
     * Renew subscription for next month
     */
    public function renew()
    {
        $newPeriodStart = $this->current_period_end->addDay();
        $newPeriodEnd = $newPeriodStart->copy()->endOfMonth();

        $this->update([
            'current_period_start' => $newPeriodStart,
            'current_period_end' => $newPeriodEnd,
            'next_billing_date' => $newPeriodEnd->addDay(),
        ]);

        return $this;
    }

    /**
     * Pause subscription
     */
    public function pause()
    {
        $this->update([
            'status' => 'paused',
            'auto_renew' => false,
        ]);
    }

    /**
     * Resume subscription
     */
    public function resume()
    {
        $this->update([
            'status' => 'active',
            'auto_renew' => true,
        ]);
    }

    /**
     * Check if subscription is active
     */
    public function isActive()
    {
        return $this->status === 'active' &&
            $this->current_period_end->gte(now());
    }

    /**
     * Get days until renewal
     */
    public function daysUntilRenewal()
    {
        return now()->diffInDays($this->next_billing_date, false);
    }

    /**
     * Get usage for current period
     */
    public function getCurrentPeriodUsage()
    {
        return AgentUsageTracking::where('tenant_id', $this->tenant_id)
            ->where('agent_id', $this->agent_id)
            ->whereBetween('executed_at', [
                $this->current_period_start,
                $this->current_period_end->endOfDay()
            ])
            ->count();
    }
}
