<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

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
        return $this->hasMany(Webhook::class, 'user_id');
    }

    /**
     * Get the API keys created by the user.
     */
    public function apiKeys(): HasMany
    {
        return $this->hasMany(APIKey::class, 'user_id');
    }

    /**
     * Get the connected apps created by the user.
     */
    public function connectedApps(): HasMany
    {
        return $this->hasMany(ConnectedApp::class, 'user_id');
    }

    /**
     * Get the user activities.
     */
    public function activities(): HasMany
    {
        return $this->hasMany(UserActivity::class, 'user_id');
    }

    /**
     * Get the user sessions.
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(UserSession::class, 'user_id');
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     */
    public function getJWTCustomClaims()
    {
        return [
            'tenant_id' => $this->tenant_id,
        ];
    }
}
