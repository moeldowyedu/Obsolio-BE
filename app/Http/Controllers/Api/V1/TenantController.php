<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
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
                    }
                ])->get();

        return response()->json([
            'data' => TenantResource::collection($tenants),
        ]);
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
            'slug' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/', 'unique:tenants,slug'],
        ]);

        try {
            return DB::transaction(function () use ($request, $validated) {
                $user = $request->user();

                // Generate slug if not provided
                $slug = $validated['slug'] ?? \Illuminate\Support\Str::slug($validated['name']);

                // Ensure slug uniqueness
                $originalSlug = $slug;
                $counter = 1;
                while (Tenant::where('slug', $slug)->exists()) {
                    $slug = $originalSlug . '-' . $counter;
                    $counter++;
                }

                // Create tenant
                $tenant = Tenant::create([
                    'id' => \Illuminate\Support\Str::uuid()->toString(),
                    'name' => $validated['name'],
                    'short_name' => $validated['short_name'] ?? null,
                    'slug' => $slug,
                    'type' => $validated['type'],
                    'status' => 'active',
                    'setup_completed' => true,
                    'setup_completed_at' => now(),
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
    public function show(): TenantResource
    {
        $tenant = tenant();

        // Ensure we have a Tenant model instance
        if (!($tenant instanceof Tenant)) {
            $tenant = Tenant::find($tenant->id);
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
    public function update(Request $request): TenantResource
    {
        $tenant = tenant();

        // Ensure we have a Tenant model instance
        if (!($tenant instanceof Tenant)) {
            $tenant = Tenant::find($tenant->id);
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'short_name' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('tenants', 'short_name')->ignore($tenant->id)
            ],
        ]);

        $tenant->update($validated);

        return new TenantResource($tenant);
    }
}
