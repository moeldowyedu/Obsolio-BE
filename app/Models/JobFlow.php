<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobFlow extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'agent_id',
        'job_title',
        'job_description',
        'organization_id',
        'branch_id',
        'department_id',
        'project_id',
        'reporting_manager_id',
        'employment_type',
        'schedule_type',
        'schedule_config',
        'input_source',
        'output_destination',
        'hitl_mode',
        'hitl_supervisor_id',
        'hitl_rules',
        'status',
        'last_run_at',
        'next_run_at',
        'total_runs',
        'successful_runs',
        'failed_runs',
    ];

    protected $casts = [
        'schedule_config' => 'array',
        'input_source' => 'array',
        'output_destination' => 'array',
        'hitl_rules' => 'array',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
        'total_runs' => 'integer',
        'successful_runs' => 'integer',
        'failed_runs' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function reportingManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporting_manager_id');
    }

    public function hitlSupervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hitl_supervisor_id');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(AgentExecution::class);
    }

    public function hitlApprovals(): HasMany
    {
        return $this->hasMany(HITLApproval::class);
    }
}