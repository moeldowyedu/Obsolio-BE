<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImpersonationLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'admin_user_id',
        'target_user_id',
        'started_at',
        'ended_at',
        'ip_address',
        'reason',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    /**
     * Get the admin user who started the impersonation.
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    /**
     * Get the target user being impersonated.
     */
    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /**
     * Check if impersonation is active.
     */
    public function isActive(): bool
    {
        return $this->started_at && !$this->ended_at;
    }

    /**
     * Get duration in minutes.
     */
    public function getDurationMinutes(): ?int
    {
        if (!$this->ended_at) {
            return null;
        }

        return $this->started_at->diffInMinutes($this->ended_at);
    }

    /**
     * End the impersonation session.
     */
    public function endSession(): void
    {
        $this->update([
            'ended_at' => now(),
        ]);
    }

    /**
     * Scope: Active impersonations.
     */
    public function scopeActive($query)
    {
        return $query->whereNotNull('started_at')
            ->whereNull('ended_at');
    }

    /**
     * Scope: By admin.
     */
    public function scopeByAdmin($query, int $adminId)
    {
        return $query->where('admin_user_id', $adminId);
    }

    /**
     * Scope: Recent logs.
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('started_at', '>=', now()->subDays($days));
    }

    /**
     * Log a new impersonation session.
     */
    public static function startImpersonation(
        int $adminUserId,
        int $targetUserId,
        ?string $reason = null,
        ?array $metadata = null
    ): self {
        return self::create([
            'admin_user_id' => $adminUserId,
            'target_user_id' => $targetUserId,
            'started_at' => now(),
            'ip_address' => request()->ip(),
            'reason' => $reason,
            'metadata' => $metadata,
        ]);
    }
}