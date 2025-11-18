<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasUuids, HasApiTokens, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
        'avatar_url',
        'status',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the tenant that owns the user.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the teams the user belongs to.
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members')
            ->withPivot('joined_at')
            ->withTimestamps();
    }

    /**
     * Get the user assignments.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(UserAssignment::class);
    }

    /**
     * Get the agents created by the user.
     */
    public function createdAgents(): HasMany
    {
        return $this->hasMany(Agent::class, 'created_by_user_id');
    }

    /**
     * Get the workflows created by the user.
     */
    public function createdWorkflows(): HasMany
    {
        return $this->hasMany(Workflow::class, 'created_by_user_id');
    }

    /**
     * Get the HITL approvals assigned to the user.
     */
    public function assignedHitlApprovals(): HasMany
    {
        return $this->hasMany(HITLApproval::class, 'assigned_to_user_id');
    }

    /**
     * Get the HITL approvals reviewed by the user.
     */
    public function reviewedHitlApprovals(): HasMany
    {
        return $this->hasMany(HITLApproval::class, 'reviewed_by_user_id');
    }

    /**
     * Get the webhooks created by the user.
     */
    public function webhooks(): HasMany
    {
        return $this->hasMany(Webhook::class, 'created_by_user_id');
    }

    /**
     * Get the API keys created by the user.
     */
    public function apiKeys(): HasMany
    {
        return $this->hasMany(APIKey::class, 'created_by_user_id');
    }
}
