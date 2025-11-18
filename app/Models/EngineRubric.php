<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EngineRubric extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'engine_id',
        'name',
        'criteria',
        'weights',
        'threshold',
        'is_default',
    ];

    protected $casts = [
        'criteria' => 'array',
        'weights' => 'array',
        'threshold' => 'decimal:2',
        'is_default' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function engine(): BelongsTo
    {
        return $this->belongsTo(Engine::class);
    }
}