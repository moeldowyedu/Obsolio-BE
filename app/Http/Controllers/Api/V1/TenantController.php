<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TenantController extends Controller
{
    /**
     * Display all tenants the user has access to.
     */
    /**
     * @OA\Get(
     *     path="/tenants",
     *     summary="Get all user's tenants",
     *     operationId="getTenants",
     *     tags={"Tenant"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="array", @OA\Items(type="object"))
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get all tenants the user is a member of through tenant_memberships
        $tenants = Tenant::whereHas('memberships', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->with([
                    'memberships' => function ($query) use ($user) {
                        $query->where('user_id', $user->id);
                    },
                    'organizations' // Eager load organizations for logo
                ])->get();

        return response()->json([
            'data' => TenantResource::collection($tenants),
        ]);
    }

    /**
     * Display all tenants (System Admin only).
     */
    public function indexAdmin(Request $request): AnonymousResourceCollection
    {
        // For system admin, list ALL tenants
        // We include organizations to ensure logo_url in resource works efficiently
        // We include ownerMembership.user to get the admin details
        // We count memberships to get total users
        $tenants = Tenant::with(['organizations', 'ownerMembership.user'])
            ->withCount('memberships')
            ->paginate(request('per_page', 15));

        return TenantResource::collection($tenants);
    }

    /**
     * Update tenant details (System Admin only).
     */
    public function updateAdmin(Request $request, string $id): TenantResource
    {
        $tenant = Tenant::findOrFail($id);

        $validated = $request->validate([
            'end_date' => ['nullable', 'date'],
            // Add other admin-editable fields here if needed
        ]);

        if (array_key_exists('end_date', $validated)) {
            $tenant->trial_ends_at = $validated['end_date'];
        }

        $tenant->save();

        // Reload relationships for the resource
        $tenant->load(['organizations', 'ownerMembership.user']);
        $tenant->loadCount('memberships');

        return new TenantResource($tenant);
    }

    /**
     * Create a new tenant.
     */
    /**
     * @OA\Post(
     *     path="/tenants",
     *     summary="Create a new tenant",
     *     operationId="createTenant",
     *     tags={"Tenant"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "type"},
     *             @OA\Property(property="name", type="string", example="My Organization"),
     *             @OA\Property(property="short_name", type="string", example="MYORG"),
     *             @OA\Property(property="type", type="string", enum={"organization", "individual"}, example="organization"),
     *             @OA\Property(property="slug", type="string", example="my-organization")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Tenant created successfully",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:50', 'unique:tenants,short_name'],
            'type' => ['required', 'string', 'in:organization,individual'],
            // 'slug' is no longer needed as a separate column, it will be the ID
            'slug' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/', 'unique:tenants,id'],
        ]);

        try {
            return DB::transaction(function () use ($request, $validated) {
                $user = $request->user();

                // Generate slug if not provided to serve as ID
                $slug = $validated['slug'] ?? \Illuminate\Support\Str::slug($validated['name']);

                // Ensure slug uniqueness for ID
                $originalSlug = $slug;
                $counter = 1;
                while (Tenant::where('id', $slug)->exists()) {
                    $slug = $originalSlug . '-' . $counter;
                    $counter++;
                }

                $id = $slug; // The ID IS the slug/subdomain

                // Create tenant
                $tenant = Tenant::create([
                    'id' => $id,
                    'name' => $validated['name'],
                    'short_name' => $validated['short_name'] ?? null,
                    'type' => $validated['type'],
                    'status' => 'active',
                ]);

                // Create Domain
                $tenant->domains()->create([
                    'domain' => $id . '.' . (config('tenancy.central_domains')[0] ?? 'obsolio.com'),
                ]);

                // Create tenant membership for the user as owner
                \App\Models\TenantMembership::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'role' => 'owner',
                ]);

                // Load the membership relationship
                $tenant->load([
                    'memberships' => function ($query) use ($user) {
                        $query->where('user_id', $user->id);
                    }
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Tenant created successfully',
                    'data' => new TenantResource($tenant),
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create tenant',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Switch the active tenant for the user.
     */
    /**
     * @OA\Post(
     *     path="/tenants/{id}/switch",
     *     summary="Switch active tenant",
     *     operationId="switchTenant",
     *     tags={"Tenant"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Tenant ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tenant switched successfully",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="User is not a member of this tenant"
     *     )
     * )
     */
    public function switch(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        // Verify the user is a member of the tenant
        $membership = \App\Models\TenantMembership::where('user_id', $user->id)
            ->where('tenant_id', $id)
            ->first();

        if (!$membership) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a member of this tenant',
            ], 403);
        }

        try {
            // Update user's current tenant_id
            $user->update(['tenant_id' => $id]);

            $tenant = Tenant::find($id);

            // Load membership for resource
            $tenant->load([
                'memberships' => function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                }
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Switched to tenant successfully',
                'data' => new TenantResource($tenant),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to switch tenant',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the current tenant.
     */
    /**
     * @OA\Get(
     *     path="/tenant",
     *     summary="Get current tenant",
     *     operationId="getTenant",
     *     tags={"Tenant"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function show(Request $request, string $id = null): TenantResource
    {
        if ($id) {
            // Verify user has access to this tenant
            $membership = \App\Models\TenantMembership::where('user_id', $request->user()->id)
                ->where('tenant_id', $id)
                ->exists();

            if (!$membership) {
                abort(403, 'Unauthorized access to this tenant.');
            }

            $tenant = Tenant::findOrFail($id);
        } else {
            $tenant = tenant();

            // Ensure we have a Tenant model instance
            if (!($tenant instanceof Tenant)) {
                $tenant = Tenant::find($tenant->id);
            }
        }

        return new TenantResource($tenant);
    }

    /**
     * Update the current tenant.
     */
    /**
     * @OA\Put(
     *     path="/tenant",
     *     summary="Update current tenant",
     *     operationId="updateTenant",
     *     tags={"Tenant"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Acme Corp"),
     *             @OA\Property(property="short_name", type="string", example="ACME")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tenant updated successfully",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function update(Request $request, string $id = null): TenantResource
    {
        if ($id) {
            // Verify user has access to this tenant
            $membership = \App\Models\TenantMembership::where('user_id', $request->user()->id)
                ->where('tenant_id', $id)
                ->exists();

            if (!$membership) {
                abort(403, 'Unauthorized access to this tenant.');
            }

            $tenant = Tenant::findOrFail($id);
        } else {
            $tenant = tenant();

            // Ensure we have a Tenant model instance
            if (!($tenant instanceof Tenant)) {
                $tenant = Tenant::find($tenant->id);
            }
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'short_name' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('tenants', 'short_name')->ignore($tenant->id)
            ],
            'organizationLogo' => ['nullable'], // Allow file or string
        ]);

        // Handle Organization Logo if present
        if ($request->hasFile('organizationLogo') || $request->input('organizationLogo')) {
            $organization = $tenant->organizations()->orderBy('created_at', 'asc')->first();

            if ($organization) {
                if ($request->hasFile('organizationLogo')) {
                    $path = $request->file('organizationLogo')->store('organizations/logos', 'public');
                    $organization->update(['logo_url' => \Illuminate\Support\Facades\Storage::url($path)]);
                } elseif (is_string($request->input('organizationLogo'))) {
                    $organization->update(['logo_url' => $request->input('organizationLogo')]);
                }
            }
        }

        $tenant->update(collect($validated)->except('organizationLogo')->toArray());

        return new TenantResource($tenant);
    }
    /**
     * Find tenant by subdomain (Public).
     * Used for the "Workspace Incomplete" page to determine status.
     */
    public function findBySubdomain(string $subdomain): JsonResponse
    {
        // Find tenant by ID (which is the subdomain/slug)
        $tenant = Tenant::with('ownerMembership.user')->find($subdomain);

        if (!$tenant) {
            return response()->json([
                'message' => 'Tenant not found',
            ], 404);
        }

        $owner = $tenant->ownerMembership?->user;
        $requiresVerification = $owner && !$owner->hasVerifiedEmail();

        return response()->json([
            'data' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'status' => $tenant->status,
                'requires_verification' => $requiresVerification,
                'owner_id' => $owner?->id,
                // Add masked email for UI if needed "Verification sent to a...s@domain.com"
                'owner_email_masked' => $owner ? $this->maskEmail($owner->email) : null,
            ]
        ]);
    }

    /**
     * Resend verification email to tenant owner (Public).
     */
    public function resendVerification(string $subdomain): JsonResponse
    {
        $tenant = Tenant::with('ownerMembership.user')->find($subdomain);

        if (!$tenant) {
            return response()->json([
                'message' => 'Tenant not found',
            ], 404);
        }

        $owner = $tenant->ownerMembership?->user;

        if (!$owner) {
            return response()->json([
                'message' => 'Tenant has no owner',
            ], 404);
        }

        if ($owner->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified',
            ], 400);
        }

        $owner->sendEmailVerificationNotification();

        return response()->json([
            'success' => true,
            'message' => 'Verification email sent',
        ]);
    }

    private function maskEmail($email)
    {
        if (!$email)
            return null;
        $parts = explode('@', $email);
        if (count($parts) != 2)
            return $email;

        $name = $parts[0];
        $domain = $parts[1];

        $len = strlen($name);
        if ($len <= 2) {
            return $name . '@' . $domain;
        }

        $maskedName = substr($name, 0, 1) . str_repeat('*', $len - 2) . substr($name, -1);
        return $maskedName . '@' . $domain;
    }
}

