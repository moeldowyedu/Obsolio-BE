<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'description',
        'long_description',
        'icon_url',

        'capabilities',
        'supported_languages',
        'price_model',
        'base_price',
        'monthly_price',
        'annual_price',
        'is_active',
        'is_featured',
        'version',
        'created_by_user_id',
        'runtime_type',
        'execution_timeout_ms',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'capabilities' => 'array',
        'supported_languages' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'base_price' => 'decimal:2',
        'monthly_price' => 'decimal:2',
        'annual_price' => 'decimal:2',
        'execution_timeout_ms' => 'integer',
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
     * Get the categories this agent belongs to.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(AgentCategory::class, 'agent_category_map', 'agent_id', 'category_id');
    }

    /**
     * Get the endpoints for this agent.
     */
    public function endpoints(): HasMany
    {
        return $this->hasMany(AgentEndpoint::class);
    }

    /**
     * Get the trigger endpoint for this agent.
     */
    public function triggerEndpoint(): HasMany
    {
        return $this->hasMany(AgentEndpoint::class)->where('type', 'trigger');
    }

    /**
     * Get the callback endpoint for this agent.
     */
    public function callbackEndpoint(): HasMany
    {
        return $this->hasMany(AgentEndpoint::class)->where('type', 'callback');
    }

    /**
     * Get the runs for this agent.
     */
    public function runs(): HasMany
    {
        return $this->hasMany(AgentRun::class);
    }

    /**
     * Check if agent is free.
     */
    public function isFree(): bool
    {
        return $this->price_model === 'free';
    }

    /**
     * Check if agent is featured.
     */
    public function isFeatured(): bool
    {
        return $this->is_featured;
    }

    /**
     * Check if agent is n8n runtime type.
     */
    public function isN8nRuntime(): bool
    {
        return $this->runtime_type === 'n8n';
    }

    /**
     * Check if agent is custom runtime type.
     */
    public function isCustomRuntime(): bool
    {
        return $this->runtime_type === 'custom';
    }

    /**
     * Scope: Active agents only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Featured agents.
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope: By runtime type.
     */
    public function scopeByRuntimeType($query, string $runtimeType)
    {
        return $query->where('runtime_type', $runtimeType);
    }
}
