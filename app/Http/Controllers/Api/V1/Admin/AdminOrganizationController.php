<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Admin - Organizations",
 *     description="Admin endpoints for organization management"
 * )
 */
class AdminOrganizationController extends Controller
{
    /**
     * List all organizations with filtering and pagination.
     *
     * @OA\Get(
     *     path="/api/v1/admin/organizations",
     *     summary="List all organizations",
     *     description="Get paginated list of all organizations with filters",
     *     operationId="adminListOrganizations",
     *     tags={"Admin - Organizations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer"), description="Page number"),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer"), description="Items per page (max 100)"),
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string"), description="Search by organization name"),
     *     @OA\Parameter(name="industry", in="query", required=false, @OA\Schema(type="string"), description="Filter by industry"),
     *     @OA\Parameter(name="company_size", in="query", required=false, @OA\Schema(type="string"), description="Filter by company size"),
     *     @OA\Response(
     *         response=200,
     *         description="Organizations retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->query('per_page', 20), 100);
        $search = $request->query('search');
        $industry = $request->query('industry');
        $companySize = $request->query('company_size');

        $query = Organization::query()
            ->with(['tenant:id,name,email,status,type'])
            ->withCount(['users', 'teams', 'projects']);

        // Search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('short_name', 'ILIKE', "%{$search}%");
            });
        }

        // Industry filter
        if ($industry) {
            $query->where('industry', $industry);
        }

        // Company size filter
        if ($companySize) {
            $query->where('company_size', $companySize);
        }

        $organizations = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $organizations,
        ]);
    }

    /**
     * Get specific organization details.
     *
     * @OA\Get(
     *     path="/api/v1/admin/organizations/{id}",
     *     summary="Get organization details",
     *     description="Get detailed information about a specific organization",
     *     operationId="adminGetOrganization",
     *     tags={"Admin - Organizations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid"), description="Organization ID"),
     *     @OA\Response(
     *         response=200,
     *         description="Organization details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Organization not found")
     * )
     */
    public function show(string $id): JsonResponse
    {
        $organization = Organization::with([
            'tenant:id,name,email,status,type',
            'branches',
            'departments',
            'teams',
            'projects',
        ])
            ->withCount(['users', 'teams', 'projects', 'branches', 'departments'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $organization,
        ]);
    }

    /**
     * Create a new organization.
     *
     * @OA\Post(
     *     path="/api/v1/admin/organizations",
     *     summary="Create new organization",
     *     description="Create a new organization for a tenant",
     *     operationId="adminCreateOrganization",
     *     tags={"Admin - Organizations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"tenant_id", "name"},
     *             @OA\Property(property="tenant_id", type="string", format="uuid"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="short_name", type="string"),
     *             @OA\Property(property="industry", type="string"),
     *             @OA\Property(property="company_size", type="string"),
     *             @OA\Property(property="country", type="string"),
     *             @OA\Property(property="phone", type="string"),
     *             @OA\Property(property="timezone", type="string"),
     *             @OA\Property(property="description", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Organization created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'tenant_id' => 'required|uuid|exists:tenants,id',
            'name' => 'required|string|max:255',
            'short_name' => 'nullable|string|alpha_dash|max:100',
            'industry' => 'nullable|string|max:100',
            'company_size' => ['nullable', Rule::in(['1-10', '11-50', '51-200', '201-500', '501-1000', '1000+'])],
            'country' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'timezone' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:1000',
        ]);

        try {
            $organization = Organization::create([
                'tenant_id' => $request->tenant_id,
                'name' => $request->name,
                'short_name' => $request->short_name ?? Str::slug($request->name),
                'industry' => $request->industry,
                'company_size' => $request->company_size,
                'country' => $request->country,
                'phone' => $request->phone,
                'timezone' => $request->timezone,
                'description' => $request->description,
                'settings' => [],
            ]);

            // Log the creation
            activity()
                ->performedOn($organization)
                ->causedBy(auth()->user())
                ->log('organization_created_by_admin');

            return response()->json([
                'success' => true,
                'message' => 'Organization created successfully',
                'data' => $organization->load('tenant'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create organization',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update organization information.
     *
     * @OA\Put(
     *     path="/api/v1/admin/organizations/{id}",
     *     summary="Update organization",
     *     description="Update organization information",
     *     operationId="adminUpdateOrganization",
     *     tags={"Admin - Organizations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid"), description="Organization ID"),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="short_name", type="string"),
     *             @OA\Property(property="industry", type="string"),
     *             @OA\Property(property="company_size", type="string"),
     *             @OA\Property(property="country", type="string"),
     *             @OA\Property(property="phone", type="string"),
     *             @OA\Property(property="timezone", type="string"),
     *             @OA\Property(property="description", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Organization updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'short_name' => 'nullable|string|alpha_dash|max:100',
            'industry' => 'nullable|string|max:100',
            'company_size' => ['nullable', Rule::in(['1-10', '11-50', '51-200', '201-500', '501-1000', '1000+'])],
            'country' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'timezone' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:1000',
        ]);

        $organization = Organization::findOrFail($id);

        try {
            $oldData = $organization->toArray();

            $organization->update($request->only([
                'name',
                'short_name',
                'industry',
                'company_size',
                'country',
                'phone',
                'timezone',
                'description',
            ]));

            // Log the update
            activity()
                ->performedOn($organization)
                ->causedBy(auth()->user())
                ->withProperties([
                    'old' => $oldData,
                    'new' => $organization->fresh()->toArray(),
                ])
                ->log('organization_updated_by_admin');

            return response()->json([
                'success' => true,
                'message' => 'Organization updated successfully',
                'data' => $organization->fresh()->load('tenant'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update organization',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Deactivate an organization (through tenant deactivation).
     *
     * @OA\Post(
     *     path="/api/v1/admin/organizations/{id}/deactivate",
     *     summary="Deactivate organization",
     *     description="Deactivate an organization by deactivating its tenant",
     *     operationId="adminDeactivateOrganization",
     *     tags={"Admin - Organizations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid"), description="Organization ID"),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="reason", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Organization deactivated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function deactivate(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $organization = Organization::with('tenant')->findOrFail($id);
        $tenant = $organization->tenant;

        if ($tenant->status === 'inactive') {
            return response()->json([
                'success' => false,
                'message' => 'Organization is already deactivated',
            ], 400);
        }

        try {
            DB::beginTransaction();

            $oldStatus = $tenant->status;

            // Deactivate the tenant (which effectively deactivates the organization)
            $tenant->update([
                'status' => 'inactive',
            ]);

            // Cancel active subscriptions
            $tenant->subscriptions()
                ->whereIn('status', ['trialing', 'active'])
                ->update([
                    'status' => 'canceled',
                    'canceled_at' => now(),
                ]);

            // Log the deactivation
            activity()
                ->performedOn($organization)
                ->causedBy(auth()->user())
                ->withProperties([
                    'old_tenant_status' => $oldStatus,
                    'new_tenant_status' => 'inactive',
                    'reason' => $request->reason,
                ])
                ->log('organization_deactivated_by_admin');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Organization deactivated successfully',
                'data' => $organization->fresh()->load('tenant'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate organization',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reactivate a deactivated organization.
     *
     * @OA\Post(
     *     path="/api/v1/admin/organizations/{id}/reactivate",
     *     summary="Reactivate organization",
     *     description="Reactivate a deactivated organization by reactivating its tenant",
     *     operationId="adminReactivateOrganization",
     *     tags={"Admin - Organizations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid"), description="Organization ID"),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="reason", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Organization reactivated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function reactivate(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $organization = Organization::with('tenant')->findOrFail($id);
        $tenant = $organization->tenant;

        if ($tenant->status === 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Organization is already active',
            ], 400);
        }

        try {
            $oldStatus = $tenant->status;

            $tenant->update([
                'status' => 'active',
            ]);

            // Log the reactivation
            activity()
                ->performedOn($organization)
                ->causedBy(auth()->user())
                ->withProperties([
                    'old_tenant_status' => $oldStatus,
                    'new_tenant_status' => 'active',
                    'reason' => $request->reason,
                ])
                ->log('organization_reactivated_by_admin');

            return response()->json([
                'success' => true,
                'message' => 'Organization reactivated successfully',
                'data' => $organization->fresh()->load('tenant'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reactivate organization',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get organization statistics.
     *
     * @OA\Get(
     *     path="/api/v1/admin/organizations/statistics",
     *     summary="Get organization statistics",
     *     description="Get statistics about all organizations",
     *     operationId="adminOrganizationStatistics",
     *     tags={"Admin - Organizations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_organizations' => Organization::count(),
            'by_industry' => Organization::select('industry', DB::raw('count(*) as count'))
                ->whereNotNull('industry')
                ->groupBy('industry')
                ->pluck('count', 'industry')
                ->toArray(),
            'by_company_size' => Organization::select('company_size', DB::raw('count(*) as count'))
                ->whereNotNull('company_size')
                ->groupBy('company_size')
                ->pluck('count', 'company_size')
                ->toArray(),
            'active' => Organization::whereHas('tenant', function ($q) {
                $q->where('status', 'active');
            })->count(),
            'inactive' => Organization::whereHas('tenant', function ($q) {
                $q->where('status', 'inactive');
            })->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
