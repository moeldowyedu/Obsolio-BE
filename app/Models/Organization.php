<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory, HasUuids;

    /**
     * The "booted" method of the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::updated(function ($organization) {
            if ($organization->isDirty(['name', 'short_name']) && $organization->tenant) {
                $organization->tenant->update([
                    'name' => $organization->name,
                    'short_name' => $organization->short_name,
                ]);
            }
        });

        static::created(function ($organization) {
            if ($organization->tenant) {
                $organization->tenant->update([
                    'name' => $organization->name,
                    'short_name' => $organization->short_name,
                ]);
            }
        });
    }

    /**
     * Retrieve the model for a bound value.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function resolveRouteBinding($value, $field = null)
    {
        // 1. Try finding by UUID
        if (\Illuminate\Support\Str::isUuid($value)) {
            $org = $this->where('id', $value)->first();
            if ($org)
                return $org;
        }

        // 2. Try finding by short_name
        $org = $this->where('short_name', $value)->first();
        if ($org)
            return $org;

        // 3. Fallback: Check if 'value' is actually a Tenant ID/Slug and create the Org
        // This handles legacy/broken states where Tenant exists but Org does not.
        $tenant = Tenant::where('id', $value)->orWhere('slug', $value)->first();

        if ($tenant) {
            // Check if this tenant already has ANY organization (maybe named differently?)
            $existingOrg = $this->where('tenant_id', $tenant->id)->first();
            if ($existingOrg)
                return $existingOrg;

            // Create the default organization
            return self::create([
                'tenant_id' => $tenant->id,
                'name' => $tenant->organization_name ?? $tenant->name ?? 'Default Organization',
                'short_name' => $tenant->slug ?? \Illuminate\Support\Str::slug($tenant->name),
                'settings' => [],
            ]);
        }

        abort(404, 'Organization not found.');
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'short_name',
        'industry',
        'company_size',
        'country',
        'phone',
        'timezone',
        'logo_url',
        'description',
        'settings',
    ];

    /**
     * Get the logo URL as an absolute path.
     */
    public function getLogoUrlAttribute(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        return asset($value);
    }

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'settings' => 'array',
    ];

    /**
     * Get the tenant that owns the organization.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the branches for the organization.
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * Get the departments for the organization.
     */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    /**
     * Get the projects for the organization.
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Get the teams for the organization.
     */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    /**
     * Get the agents for the organization.
     */
    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }

    /**
     * Get the workflows for the organization.
     */
    public function workflows(): HasMany
    {
        return $this->hasMany(Workflow::class);
    }
}
