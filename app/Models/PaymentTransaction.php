<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'invoice_id',
        'paymob_transaction_id',
        'paymob_order_id',
        'merchant_order_id',
        'amount',
        'currency',
        'status',
        'payment_method',
        'card_last_four',
        'card_brand',
        'description',
        'metadata',
        'paymob_response',
        'is_refunded',
        'refunded_at',
        'refund_amount',
        'paid_at',
        'failed_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'metadata' => 'array',
        'paymob_response' => 'array',
        'is_refunded' => 'boolean',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    /**
     * Get the tenant that owns the transaction.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the subscription associated with the transaction.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Get the invoice associated with the transaction.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(BillingInvoice::class, 'invoice_id');
    }

    /**
     * Check if transaction is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if transaction is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending' || $this->status === 'processing';
    }

    /**
     * Check if transaction failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Mark transaction as completed.
     */
    public function markAsCompleted(array $paymobData = []): void
    {
        $this->update([
            'status' => 'completed',
            'paid_at' => now(),
            'paymob_response' => $paymobData,
        ]);
    }

    /**
     * Mark transaction as failed.
     */
    public function markAsFailed(array $paymobData = []): void
    {
        $this->update([
            'status' => 'failed',
            'failed_at' => now(),
            'paymob_response' => $paymobData,
        ]);
    }

    /**
     * Mark transaction as refunded.
     */
    public function markAsRefunded(float $amount, array $metadata = []): void
    {
        $this->update([
            'status' => 'refunded',
            'is_refunded' => true,
            'refunded_at' => now(),
            'refund_amount' => $amount,
            'metadata' => array_merge($this->metadata ?? [], $metadata),
        ]);
    }

    /**
     * Scope to get completed transactions.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to get pending transactions.
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', ['pending', 'processing']);
    }

    /**
     * Scope to get failed transactions.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
