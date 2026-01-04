<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceLineItem extends Model
{
    // No updated_at column
    const UPDATED_AT = null;

    protected $fillable = [
        'invoice_id',
        'item_type',
        'description',
        'quantity',
        'unit_price',
        'total_price',
        'agent_id',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:4',
        'total_price' => 'decimal:2',
        'metadata' => 'array',
    ];

    // RELATIONSHIPS

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    // SCOPES

    public function scopeOfType($query, $type)
    {
        return $query->where('item_type', $type);
    }

    public function scopeForAgent($query, $agentId)
    {
        return $query->where('agent_id', $agentId);
    }

    // STATIC METHODS - Line Item Creation

    /**
     * Create base plan line item
     */
    public static function createBasePlan(Invoice $invoice, SubscriptionPlan $plan, $amount)
    {
        return static::create([
            'invoice_id' => $invoice->id,
            'item_type' => 'base_plan',
            'description' => sprintf(
                '%s Plan (%s)',
                $plan->name,
                $plan->billingCycle->name ?? 'Monthly'
            ),
            'quantity' => 1,
            'unit_price' => $amount,
            'total_price' => $amount,
            'metadata' => [
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'billing_cycle' => $plan->billingCycle->code ?? 'monthly',
            ],
        ]);
    }

    /**
     * Create agent addon line item
     */
    public static function createAgentAddon(
        Invoice $invoice,
        Agent $agent,
        $monthlyPrice,
        $daysInPeriod = 30
    ) {
        return static::create([
            'invoice_id' => $invoice->id,
            'item_type' => 'agent_addon',
            'description' => sprintf(
                '%s Agent (%d days)',
                $agent->name,
                $daysInPeriod
            ),
            'quantity' => 1,
            'unit_price' => $monthlyPrice,
            'total_price' => $monthlyPrice,
            'agent_id' => $agent->id,
            'metadata' => [
                'agent_name' => $agent->name,
                'tier' => $agent->tier->name ?? null,
                'days_in_period' => $daysInPeriod,
            ],
        ]);
    }

    /**
     * Create usage overage line item
     */
    public static function createUsageOverage(
        Invoice $invoice,
        $executionCount,
        $pricePerExecution,
        ?Agent $agent = null
    ) {
        $total = $executionCount * $pricePerExecution;

        return static::create([
            'invoice_id' => $invoice->id,
            'item_type' => 'usage_overage',
            'description' => sprintf(
                'Execution Overage: %d executions @ $%s each%s',
                $executionCount,
                number_format($pricePerExecution, 4),
                $agent ? " ({$agent->name})" : ''
            ),
            'quantity' => $executionCount,
            'unit_price' => $pricePerExecution,
            'total_price' => $total,
            'agent_id' => $agent?->id,
            'metadata' => [
                'execution_count' => $executionCount,
                'price_per_execution' => $pricePerExecution,
            ],
        ]);
    }

    /**
     * Create discount line item
     */
    public static function createDiscount(
        Invoice $invoice,
        $description,
        $amount
    ) {
        return static::create([
            'invoice_id' => $invoice->id,
            'item_type' => 'discount',
            'description' => $description,
            'quantity' => 1,
            'unit_price' => -abs($amount), // Negative for discount
            'total_price' => -abs($amount),
        ]);
    }

    /**
     * Create tax line item
     */
    public static function createTax(
        Invoice $invoice,
        $description,
        $amount
    ) {
        return static::create([
            'invoice_id' => $invoice->id,
            'item_type' => 'tax',
            'description' => $description,
            'quantity' => 1,
            'unit_price' => $amount,
            'total_price' => $amount,
        ]);
    }
}
