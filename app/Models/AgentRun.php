<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentRun extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'agent_runs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'agent_id',
        'state',
        'input',
        'output',
        'error',
        'started_at',
        'finished_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'input' => 'array',
        'output' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    /**
     * Get the agent that owns this run.
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * Get all events for this run.
     */
    public function events(): HasMany
    {
        return $this->hasMany(AgentRunEvent::class, 'run_id');
    }

    /**
     * Check if run is finished.
     */
    public function isFinished(): bool
    {
        return in_array($this->state, ['completed', 'failed', 'cancelled', 'timeout']);
    }

    /**
     * Calculate duration in milliseconds.
     */
    public function getDurationMsAttribute(): ?int
    {
        if (!$this->started_at || !$this->finished_at) {
            return null;
        }

        return $this->started_at->diffInMilliseconds($this->finished_at);
    }

    /**
     * Scope: Filter by state.
     */
    public function scopeByState($query, string $state)
    {
        return $query->where('state', $state);
    }

    /**
     * Scope: Pending runs.
     */
    public function scopePending($query)
    {
        return $query->where('state', 'pending');
    }

    /**
     * Scope: Running runs.
     */
    public function scopeRunning($query)
    {
        return $query->where('state', 'running');
    }

    /**
     * Scope: Completed runs.
     */
    public function scopeCompleted($query)
    {
        return $query->where('state', 'completed');
    }

    /**
     * Scope: Failed runs.
     */
    public function scopeFailed($query)
    {
        return $query->where('state', 'failed');
    }
}
