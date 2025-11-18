<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'created_by_user_id',
        'name',
        'description',
        'type',
        'engines_used',
        'config',
        'input_schema',
        'output_schema',
        'rubric_config',
        'status',
        'is_published',
        'marketplace_listing_id',
        'version',
    ];

    protected $casts = [
        'engines_used' => 'array',
        'config' => 'array',
        'input_schema' => 'array',
        'output_schema' => 'array',
        'rubric_config' => 'array',
        'is_published' => 'boolean',
        'version' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function jobFlows(): HasMany
    {
        return $this->hasMany(JobFlow::class);
    }

    public function executions(): HasMany
    {
        return $this->hasMany(AgentExecution::class);
    }

    public function marketplaceListing()
    {
        return $this->hasOne(MarketplaceListing::class);
    }
}