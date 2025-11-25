<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\StoreBranchRequest;
use App\Http\Requests\Organizations\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use App\Models\UserActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BranchController extends Controller
{
    /**
     * @OA\Get(
     *     path="/branches",
     *     summary="List branches",
     *     operationId="getBranches",
     *     tags={"Branches"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="organization_id",
     *         in="query",
     *         description="Filter by organization ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", example=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="New York Branch"),
     *                     @OA\Property(property="code", type="string", example="NY-001"),
     *                     @OA\Property(property="address", type="string", example="123 Main St, New York, NY 10001"),
     *                     @OA\Property(property="phone", type="string", example="+1-212-555-0100"),
     *                     @OA\Property(property="email", type="string", example="ny.branch@example.com"),
     *                     @OA\Property(property="manager_id", type="integer", example=5),
     *                     @OA\Property(property="organization_id", type="integer", example=1),
     *                     @OA\Property(property="departments_count", type="integer", example=3),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-15T10:30:00Z")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(): AnonymousResourceCollection
    {
        $branches = Branch::whereHas('organization', function ($query) {
            $query->where('tenant_id', tenant('id'));
        })
            ->with(['organization', 'manager'])
            ->withCount('departments')
            ->when(request('organization_id'), function ($query, $orgId) {
                $query->where('organization_id', $orgId);
            })
            ->paginate(request('per_page', 15));

        return BranchResource::collection($branches);
    }

    /**
     * @OA\Post(
     *     path="/branches",
     *     summary="Create branch",
     *     operationId="createBranch",
     *     tags={"Branches"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","organization_id"},
     *             @OA\Property(property="name", type="string", example="Los Angeles Branch"),
     *             @OA\Property(property="code", type="string", example="LA-001"),
     *             @OA\Property(property="address", type="string", example="456 Sunset Blvd, Los Angeles, CA 90028"),
     *             @OA\Property(property="phone", type="string", example="+1-323-555-0200"),
     *             @OA\Property(property="email", type="string", example="la.branch@example.com"),
     *             @OA\Property(property="manager_id", type="integer", example=7),
     *             @OA\Property(property="organization_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Branch created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="name", type="string", example="Los Angeles Branch"),
     *                 @OA\Property(property="code", type="string", example="LA-001"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-15T11:00:00Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(StoreBranchRequest $request): JsonResponse
    {
        $branch = Branch::create($request->validated());

        $this->logActivity('create', 'create', 'Branch', $branch->id, "Branch created: {$branch->name}");

        return (new BranchResource($branch->load('organization', 'branchManager')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @OA\Get(
     *     path="/branches/{branch}",
     *     summary="Get branch details",
     *     operationId="getBranch",
     *     tags={"Branches"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="branch",
     *         in="path",
     *         description="Branch ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="New York Branch"),
     *                 @OA\Property(property="code", type="string", example="NY-001"),
     *                 @OA\Property(property="address", type="string", example="123 Main St, New York, NY 10001"),
     *                 @OA\Property(property="phone", type="string", example="+1-212-555-0100"),
     *                 @OA\Property(property="email", type="string", example="ny.branch@example.com"),
     *                 @OA\Property(property="manager_id", type="integer", example=5),
     *                 @OA\Property(property="organization", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Acme Corporation")
     *                 ),
     *                 @OA\Property(property="departments_count", type="integer", example=3)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Branch not found")
     * )
     */
    public function show(Branch $branch): BranchResource
    {
        $this->authorize('view', $branch);

        $branch->load(['organization', 'branchManager'])
            ->loadCount('departments');

        $this->logActivity('api_call', 'read', 'Branch', $branch->id, "Viewed branch: {$branch->name}");

        return new BranchResource($branch);
    }

    /**
     * @OA\Put(
     *     path="/branches/{branch}",
     *     summary="Update branch",
     *     operationId="updateBranch",
     *     tags={"Branches"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="branch",
     *         in="path",
     *         description="Branch ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="New York Headquarters"),
     *             @OA\Property(property="phone", type="string", example="+1-212-555-0101"),
     *             @OA\Property(property="email", type="string", example="ny.hq@example.com"),
     *             @OA\Property(property="manager_id", type="integer", example=6)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Branch updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="New York Headquarters"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-15T12:00:00Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=404, description="Branch not found")
     * )
     */
    public function update(UpdateBranchRequest $request, Branch $branch): BranchResource
    {
        $this->authorize('update', $branch);

        $branch->update($request->validated());

        $this->logActivity('update', 'update', 'Branch', $branch->id, "Branch updated: {$branch->name}");

        return new BranchResource($branch->load('organization', 'branchManager'));
    }

    /**
     * @OA\Delete(
     *     path="/branches/{branch}",
     *     summary="Delete branch",
     *     operationId="deleteBranch",
     *     tags={"Branches"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="branch",
     *         in="path",
     *         description="Branch ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Branch deleted successfully"
     *     ),
     *     @OA\Response(response=404, description="Branch not found"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function destroy(Branch $branch): JsonResponse
    {
        $this->authorize('delete', $branch);

        $name = $branch->name;
        $branch->delete();

        $this->logActivity('delete', 'delete', 'Branch', $branch->id, "Branch deleted: {$name}");

        return response()->json(null, 204);
    }

    /**
     * Log user activity.
     */
    private function logActivity(
        string $activityType,
        string $action,
        string $entityType,
        ?string $entityId,
        string $description,
        string $status = 'success',
        ?string $errorMessage = null
    ): void {
        UserActivity::create([
            'tenant_id' => tenant('id'),
            'user_id' => request()->user()->id,
            'organization_id' => request()->user()->organization_id ?? null,
            'activity_type' => $activityType,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => $description,
            'metadata' => [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'request_id' => request()->header('X-Request-ID'),
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'status' => $status,
            'error_message' => $errorMessage,
            'is_sensitive' => false,
            'requires_audit' => false,
        ]);
    }
}
