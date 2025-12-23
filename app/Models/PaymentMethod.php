<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMethod extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'type',
        'stripe_payment_method_id',
        'is_default',
        'last4',
        'brand',
        'exp_month',
        'exp_month',
        'exp_year',
        'country',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
        'is_default' => 'boolean',
    ];

    /**
     * Get the tenant that owns the payment method.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Check if payment method is default.
     */
    public function isDefault(): bool
    {
        return $this->is_default;
    }

    /**
     * Check if card is expired.
     */
    public function isExpired(): bool
    {
        if (!$this->exp_month || !$this->exp_year) {
            return false;
        }

        $expDate = now()->createFromDate($this->exp_year, $this->exp_month, 1)->endOfMonth();

        return $expDate->isPast();
    }

    /**
     * Set as default payment method.
     */
    public function setAsDefault(): void
    {
        // Remove default from all other payment methods
        self::where('tenant_id', $this->tenant_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        // Set this as default
        $this->update(['is_default' => true]);
    }

    /**
     * Get masked card number.
     */
    public function getMaskedNumber(): string
    {
        return '**** **** **** ' . $this->last4;
    }
}