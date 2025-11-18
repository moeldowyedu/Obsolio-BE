<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageRecord extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'record_date',
        'agent_executions',
        'api_calls',
        'storage_bytes',
        'webhook_deliveries',
    ];

    protected $casts = [
        'record_date' => 'date',
        'agent_executions' => 'integer',
        'api_calls' => 'integer',
        'storage_bytes' => 'integer',
        'webhook_deliveries' => 'integer',
        'created_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}