<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplaceListing extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'agent_id',
        'seller_tenant_id',
        'title',
        'description',
        'category',
        'industry',
        'price_type',
        'price',
        'currency',
        'thumbnail_url',
        'screenshots',
        'tags',
        'is_featured',
        'is_approved',
        'status',
        'views_count',
        'purchases_count',
        'rating_average',
        'reviews_count',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'screenshots' => 'array',
        'tags' => 'array',
        'is_featured' => 'boolean',
        'is_approved' => 'boolean',
        'views_count' => 'integer',
        'purchases_count' => 'integer',
        'rating_average' => 'decimal:2',
        'reviews_count' => 'integer',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function sellerTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'seller_tenant_id');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(MarketplacePurchase::class, 'listing_id');
    }
}