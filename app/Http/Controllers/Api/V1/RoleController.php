<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Roles\StoreRoleRequest;
use App\Http\Requests\Roles\UpdateRoleRequest;
use App\Models\UserActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * System roles that cannot be modified or deleted.
     */
    private const SYSTEM_ROLES = [
        'Super Admin',
        'Admin',
        'Organization Manager',
        'Project Manager',
        'Team Lead',
        'Developer',
        'Agent Designer',
        'Viewer',
    ];

    /**
     * Display a listing of roles.
     */
    /**
     * @OA\Get(
     *     path="/roles",
     *     summary="List roles",
     *     operationId="getRoles",
     *     tags={"Roles"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filter by type (system/custom)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"system", "custom"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('view-roles');

        $roles = Role::with('permissions')
            ->when($request->has('type'), function ($query) use ($request) {
                if ($request->type === 'system') {
                    $query->whereIn('name', self::SYSTEM_ROLES);
                } elseif ($request->type === 'custom') {
                    $query->whereNotIn('name', self::SYSTEM_ROLES);
                }
            })
            ->get()
            ->map(function ($role) {
                $role->is_system = in_array($role->name, self::SYSTEM_ROLES);
                return $role;
            });

        $this->logActivity('api_call', 'read', 'Role', null, 'Listed roles');

        return response()->json([
            'success' => true,
            'data' => $roles,
        ]);
    }

    /**
     * Store a newly created role.
     */
    /**
     * @OA\Post(
     *     path="/roles",
     *     summary="Create role",
     *     operationId="createRole",
     *     tags={"Roles"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Role created successfully",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        try {
            $role = Role::create([
                'name' => $request->name,
                'guard_name' => $request->guard_name ?? 'web',
            ]);

            // Attach permissions if provided
            if ($request->has('permissions') && is_array($request->permissions)) {
                $role->givePermissionTo($request->permissions);
            }

            $role->load('permissions');
            $role->is_system = false;

            $this->logActivity('create', 'create', 'Role', $role->id, "Role created: {$role->name}");

            return response()->json([
                'success' => true,
                'message' => 'Role created successfully',
                'data' => $role,
            ], 201);
        } catch (\Exception $e) {
            $this->logActivity('create', 'create', 'Role', null, 'Failed to create role', 'failure', $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create role',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified role.
     */
    /**
     * @OA\Get(
     *     path="/roles/{id}",
     *     summary="Get role details",
     *     operationId="getRole",
     *     tags={"Roles"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Role ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=404, description="Role not found")
     * )
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $this->authorize('view-roles');

        $role = Role::with('permissions')->findOrFail($id);
        $role->is_system = in_array($role->name, self::SYSTEM_ROLES);

        // Get users count
        $role->users_count = $role->users()->count();

        $this->logActivity('api_call', 'read', 'Role', $role->id, "Viewed role: {$role->name}");

        return response()->json([
            'success' => true,
            'data' => $role,
        ]);
    }

    /**
     * Update the specified role.
     */
    /**
     * @OA\Put(
     *     path="/roles/{id}",
     *     summary="Update role",
     *     operationId="updateRole",
     *     tags={"Roles"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Role ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Role updated successfully",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(UpdateRoleRequest $request, string $id): JsonResponse
    {
        try {
            $role = Role::findOrFail($id);

            // Protect system roles
            if (in_array($role->name, self::SYSTEM_ROLES)) {
                $this->logActivity('update', 'update', 'Role', $role->id, "Attempted to modify system role: {$role->name}", 'failure', 'System roles cannot be modified');

                return response()->json([
                    'success' => false,
                    'message' => 'System roles cannot be modified',
                ], 403);
            }

            // Update role name if provided
            if ($request->has('name')) {
                $role->update(['name' => $request->name]);
            }

            // Sync permissions if provided
            if ($request->has('permissions') && is_array($request->permissions)) {
                $role->syncPermissions($request->permissions);
            }

            $role->load('permissions');
            $role->is_system = false;

            $this->logActivity('update', 'update', 'Role', $role->id, "Role updated: {$role->name}");

            return response()->json([
                'success' => true,
                'message' => 'Role updated successfully',
                'data' => $role,
            ]);
        } catch (\Exception $e) {
            $this->logActivity('update', 'update', 'Role', $id, 'Failed to update role', 'failure', $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update role',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified role.
     */
    /**
     * @OA\Delete(
     *     path="/roles/{id}",
     *     summary="Delete role",
     *     operationId="deleteRole",
     *     tags={"Roles"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Role ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Role deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Role not found")
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        $this->authorize('delete-roles');

        try {
            $role = Role::findOrFail($id);

            // Protect system roles
            if (in_array($role->name, self::SYSTEM_ROLES)) {
                $this->logActivity('delete', 'delete', 'Role', $role->id, "Attempted to delete system role: {$role->name}", 'failure', 'System roles cannot be deleted');

                return response()->json([
                    'success' => false,
                    'message' => 'System roles cannot be deleted',
                ], 403);
            }

            // Check if role has users
            $usersCount = $role->users()->count();
            if ($usersCount > 0) {
                $this->logActivity('delete', 'delete', 'Role', $role->id, "Attempted to delete role with users: {$role->name}", 'failure', "Role has {$usersCount} assigned users");

                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete role that has {$usersCount} assigned user(s). Please reassign users first.",
                ], 422);
            }

            $name = $role->name;

            // Detach all permissions
            $role->syncPermissions([]);

            // Delete role
            $role->delete();

            $this->logActivity('delete', 'delete', 'Role', $id, "Role deleted: {$name}");

            return response()->json([
                'success' => true,
                'message' => 'Role deleted successfully',
            ]);
        } catch (\Exception $e) {
            $this->logActivity('delete', 'delete', 'Role', $id, 'Failed to delete role', 'failure', $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete role',
                'error' => $e->getMessage(),
            ], 500);
        }
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
