<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Agent extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'category',
        'description',
        'long_description',
        'icon_url',
        'banner_url',
        'capabilities',
        'supported_languages',
        'price_model',
        'base_price',
        'monthly_price',
        'annual_price',
        'is_marketplace',
        'is_active',
        'is_featured',
        'version',
        'total_installs',
        'rating',
        'review_count',
        'created_by_user_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'capabilities' => 'array',
        'supported_languages' => 'array',
        'is_marketplace' => 'boolean',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'base_price' => 'decimal:2',
        'monthly_price' => 'decimal:2',
        'annual_price' => 'decimal:2',
        'rating' => 'decimal:2',
    ];

    /**
     * Get the user who created this agent.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Get the tenants that have this agent.
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_agents')
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
     * Check if agent is free.
     */
    public function isFree(): bool
    {
        return $this->price_model === 'free';
    }

    /**
     * Check if agent is marketplace agent.
     */
    public function isMarketplace(): bool
    {
        return $this->is_marketplace;
    }

    /**
     * Check if agent is featured.
     */
    public function isFeatured(): bool
    {
        return $this->is_featured;
    }

    /**
     * Increment total installs.
     */
    public function incrementInstalls(): void
    {
        $this->increment('total_installs');
    }

    /**
     * Update rating.
     */
    public function updateRating(float $newRating): void
    {
        $totalRatings = $this->review_count;
        $currentTotal = $this->rating * $totalRatings;
        $newTotal = $currentTotal + $newRating;
        $newCount = $totalRatings + 1;

        $this->update([
            'rating' => round($newTotal / $newCount, 2),
            'review_count' => $newCount,
        ]);
    }

    /**
     * Scope: Active agents only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Marketplace agents only.
     */
    public function scopeMarketplace($query)
    {
        return $query->where('is_marketplace', true);
    }

    /**
     * Scope: Featured agents.
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope: By category.
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}