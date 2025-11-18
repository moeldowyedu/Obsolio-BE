<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HITLApproval extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $table = 'hitl_approvals';

    protected $fillable = [
        'tenant_id',
        'job_flow_id',
        'agent_id',
        'execution_id',
        'task_data',
        'ai_decision',
        'ai_confidence',
        'rubric_score',
        'status',
        'assigned_to_user_id',
        'reviewed_by_user_id',
        'reviewed_at',
        'reviewer_comments',
        'priority',
        'expires_at',
    ];

    protected $casts = [
        'task_data' => 'array',
        'ai_decision' => 'array',
        'ai_confidence' => 'decimal:2',
        'rubric_score' => 'decimal:2',
        'reviewed_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function jobFlow(): BelongsTo
    {
        return $this->belongsTo(JobFlow::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}