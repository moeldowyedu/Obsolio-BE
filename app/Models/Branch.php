<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'organization_id',
        'name',
        'location',
        'branch_code',
        'branch_manager_id',
        'status',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function branchManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'branch_manager_id');
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function jobFlows(): HasMany
    {
        return $this->hasMany(JobFlow::class);
    }
}
