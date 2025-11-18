<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentExecution extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'agent_id',
        'job_flow_id',
        'workflow_execution_id',
        'triggered_by',
        'triggered_by_user_id',
        'input_data',
        'output_data',
        'status',
        'started_at',
        'completed_at',
        'duration_seconds',
        'error_message',
        'rubric_scores',
        'hitl_status',
        'logs',
    ];

    protected $casts = [
        'input_data' => 'array',
        'output_data' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'duration_seconds' => 'integer',
        'rubric_scores' => 'array',
        'created_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function jobFlow(): BelongsTo
    {
        return $this->belongsTo(JobFlow::class);
    }

    public function workflowExecution(): BelongsTo
    {
        return $this->belongsTo(WorkflowExecution::class);
    }

    public function triggeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }
}