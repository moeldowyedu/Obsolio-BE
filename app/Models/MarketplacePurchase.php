<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplacePurchase extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'listing_id',
        'buyer_tenant_id',
        'purchased_by_user_id',
        'price_paid',
        'currency',
    ];

    protected $casts = [
        'price_paid' => 'decimal:2',
        'purchased_at' => 'datetime',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(MarketplaceListing::class);
    }

    public function buyerTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'buyer_tenant_id');
    }

    public function purchasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchased_by_user_id');
    }
}