<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConnectedApp extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'organization_id',
        'app_name',
        'app_type',
        'provider',
        'description',
        'client_id',
        'client_secret',
        'credentials',
        'scopes',
        'settings',
        'status',
        'callback_url',
        'token_expires_at',
        'last_synced_at',
        'last_used_at',
        'total_requests',
        'failed_requests',
    ];

    protected $casts = [
        'credentials' => 'encrypted:array',
        'scopes' => 'array',
        'settings' => 'array',
        'token_expires_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'last_used_at' => 'datetime',
        'total_requests' => 'integer',
        'failed_requests' => 'integer',
    ];

    protected $hidden = [
        'client_secret',
        'credentials',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ConnectedAppLog::class);
    }

    public function isExpired(): bool
    {
        return $this->token_expires_at && $this->token_expires_at->isPast();
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && !$this->isExpired();
    }
}
