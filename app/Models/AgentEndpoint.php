<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentEndpoint extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'agent_id',
        'type',
        'url',
        'method',
        'headers',
        'secret',
        'timeout_ms',
        'retries',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'headers' => 'array',
        'is_active' => 'boolean',
        'timeout_ms' => 'integer',
        'retries' => 'integer',
    ];

    /**
     * Get the agent that owns this endpoint.
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * Check if this is a trigger endpoint.
     */
    public function isTrigger(): bool
    {
        return $this->type === 'trigger';
    }

    /**
     * Check if this is a callback endpoint.
     */
    public function isCallback(): bool
    {
        return $this->type === 'callback';
    }

    /**
     * Validate the secret token.
     */
    public function validateSecret(string $secret): bool
    {
        return hash_equals($this->secret, $secret);
    }

    /**
     * Scope: Active endpoints only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Filter by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: Trigger endpoints only.
     */
    public function scopeTrigger($query)
    {
        return $query->where('type', 'trigger');
    }

    /**
     * Scope: Callback endpoints only.
     */
    public function scopeCallback($query)
    {
        return $query->where('type', 'callback');
    }
}
