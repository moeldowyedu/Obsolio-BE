<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentUsageTracking extends Model
{
    // No updated_at column
    const UPDATED_AT = null;

    protected $table = 'agent_usage_tracking';

    protected $fillable = [
        'tenant_id',
        'agent_id',
        'execution_id',
        'task_type',
        'tokens_used',
        'execution_time_ms',
        'ai_model_cost',
        'charged_amount',
        'metadata',
        'executed_at',
        'billing_cycle_month',
    ];

    protected $casts = [
        'tokens_used' => 'integer',
        'execution_time_ms' => 'integer',
        'ai_model_cost' => 'decimal:6',
        'charged_amount' => 'decimal:4',
        'metadata' => 'array',
        'executed_at' => 'datetime',
        'billing_cycle_month' => 'date',
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

    public function execution()
    {
        return $this->belongsTo(AgentExecution::class, 'execution_id');
    }

    // SCOPES

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForAgent($query, $agentId)
    {
        return $query->where('agent_id', $agentId);
    }

    public function scopeForMonth($query, $year, $month)
    {
        return $query->whereYear('billing_cycle_month', $year)
            ->whereMonth('billing_cycle_month', $month);
    }

    public function scopeCurrentMonth($query)
    {
        return $query->whereYear('billing_cycle_month', now()->year)
            ->whereMonth('billing_cycle_month', now()->month);
    }

    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('executed_at', [$startDate, $endDate]);
    }

    // STATIC METHODS

    /**
     * Track a new execution
     */
    public static function trackExecution(array $data)
    {
        // Set billing cycle month (first day of current month)
        $data['billing_cycle_month'] = now()->startOfMonth()->toDateString();

        // Set executed_at if not provided
        if (!isset($data['executed_at'])) {
            $data['executed_at'] = now();
        }

        return static::create($data);
    }

    /**
     * Get usage summary for tenant
     */
    public static function getTenantSummary($tenantId, $year = null, $month = null)
    {
        $query = static::where('tenant_id', $tenantId);

        if ($year && $month) {
            $query->forMonth($year, $month);
        } else {
            $query->currentMonth();
        }

        return $query->selectRaw('
            COUNT(*) as total_executions,
            SUM(ai_model_cost) as total_cost,
            SUM(charged_amount) as total_charged,
            AVG(execution_time_ms) as avg_execution_time,
            SUM(tokens_used) as total_tokens
        ')->first();
    }

    /**
     * Get usage by agent for tenant
     */
    public static function getAgentBreakdown($tenantId, $year = null, $month = null)
    {
        $query = static::where('tenant_id', $tenantId);

        if ($year && $month) {
            $query->forMonth($year, $month);
        } else {
            $query->currentMonth();
        }

        return $query->selectRaw('
            agent_id,
            COUNT(*) as executions,
            SUM(ai_model_cost) as cost,
            SUM(charged_amount) as charged
        ')
            ->groupBy('agent_id')
            ->with('agent:id,name')
            ->get();
    }

    /**
     * Get daily usage trend
     */
    public static function getDailyTrend($tenantId, $days = 30)
    {
        return static::where('tenant_id', $tenantId)
            ->where('executed_at', '>=', now()->subDays($days))
            ->selectRaw('
                DATE(executed_at) as date,
                COUNT(*) as executions,
                SUM(charged_amount) as charged
            ')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }
}
