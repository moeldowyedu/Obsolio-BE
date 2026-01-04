<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Invoice extends Model
{
    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'invoice_number',
        'base_subscription_amount',
        'agent_addons_amount',
        'usage_overage_amount',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'status',
        'billing_period_start',
        'billing_period_end',
        'due_date',
        'paid_at',
        'payment_method',
        'payment_transaction_id',
        'notes',
    ];

    protected $casts = [
        'base_subscription_amount' => 'decimal:2',
        'agent_addons_amount' => 'decimal:2',
        'usage_overage_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'billing_period_start' => 'date',
        'billing_period_end' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
    ];

    // RELATIONSHIPS

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function lineItems()
    {
        return $this->hasMany(InvoiceLineItem::class);
    }

    // SCOPES

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'pending')
            ->whereDate('due_date', '<', now());
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForPeriod($query, $year, $month)
    {
        return $query->whereYear('billing_period_start', $year)
            ->whereMonth('billing_period_start', $month);
    }

    // METHODS - Invoice Number Generation

    /**
     * Generate unique invoice number
     */
    public static function generateInvoiceNumber()
    {
        $year = now()->year;
        $month = now()->format('m');

        // Get count of invoices this month
        $count = static::whereYear('created_at', $year)
            ->whereMonth('created_at', now()->month)
            ->count() + 1;

        return sprintf('INV-%d-%s-%04d', $year, $month, $count);
    }

    /**
     * Generate invoice number and save
     */
    public function assignInvoiceNumber()
    {
        if (!$this->invoice_number) {
            $this->invoice_number = static::generateInvoiceNumber();
            $this->save();
        }

        return $this->invoice_number;
    }

    // METHODS - Payment

    /**
     * Mark invoice as paid
     */
    public function markAsPaid($transactionId = null, $paymentMethod = 'paymob')
    {
        $this->update([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_transaction_id' => $transactionId,
            'payment_method' => $paymentMethod,
        ]);

        return $this;
    }

    /**
     * Mark as failed
     */
    public function markAsFailed($reason = null)
    {
        $this->update([
            'status' => 'failed',
            'notes' => $this->notes ? $this->notes . "\n" . $reason : $reason,
        ]);

        return $this;
    }

    /**
     * Refund invoice
     */
    public function refund($reason = null)
    {
        $this->update([
            'status' => 'refunded',
            'notes' => $this->notes ? $this->notes . "\n" . $reason : $reason,
        ]);

        return $this;
    }

    /**
     * Cancel invoice
     */
    public function cancel($reason = null)
    {
        $this->update([
            'status' => 'cancelled',
            'notes' => $this->notes ? $this->notes . "\n" . $reason : $reason,
        ]);

        return $this;
    }

    // METHODS - Status Checks

    public function isPaid()
    {
        return $this->status === 'paid';
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isOverdue()
    {
        return $this->status === 'pending' && $this->due_date->lt(now());
    }

    public function getDaysOverdue()
    {
        if (!$this->isOverdue()) {
            return 0;
        }

        return now()->diffInDays($this->due_date);
    }

    // METHODS - Calculations

    /**
     * Calculate subtotal (before tax)
     */
    public function getSubtotalAttribute()
    {
        return $this->base_subscription_amount +
            $this->agent_addons_amount +
            $this->usage_overage_amount -
            $this->discount_amount;
    }

    /**
     * Add line item
     */
    public function addLineItem(array $data)
    {
        return $this->lineItems()->create($data);
    }

    /**
     * Recalculate total from line items
     */
    public function recalculateTotal()
    {
        $baseItems = $this->lineItems()
            ->where('item_type', 'base_plan')
            ->sum('total_price');

        $addonItems = $this->lineItems()
            ->where('item_type', 'agent_addon')
            ->sum('total_price');

        $overageItems = $this->lineItems()
            ->where('item_type', 'usage_overage')
            ->sum('total_price');

        $discountItems = $this->lineItems()
            ->where('item_type', 'discount')
            ->sum('total_price');

        $taxItems = $this->lineItems()
            ->where('item_type', 'tax')
            ->sum('total_price');

        $this->update([
            'base_subscription_amount' => $baseItems,
            'agent_addons_amount' => $addonItems,
            'usage_overage_amount' => $overageItems,
            'discount_amount' => abs($discountItems), // Store as positive
            'tax_amount' => $taxItems,
            'total_amount' => $baseItems + $addonItems + $overageItems - abs($discountItems) + $taxItems,
        ]);

        return $this;
    }

    // STATIC METHODS - Invoice Creation

    /**
     * Create invoice for tenant
     */
    public static function createForTenant(
        Tenant $tenant,
        Carbon $periodStart,
        Carbon $periodEnd,
        ?Subscription $subscription = null
    ) {
        $invoice = static::create([
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription?->id,
            'invoice_number' => static::generateInvoiceNumber(),
            'billing_period_start' => $periodStart,
            'billing_period_end' => $periodEnd,
            'due_date' => $periodEnd->copy()->addDays(7),
            'status' => 'pending',
            'total_amount' => 0, // Will be calculated
        ]);

        return $invoice;
    }
}
