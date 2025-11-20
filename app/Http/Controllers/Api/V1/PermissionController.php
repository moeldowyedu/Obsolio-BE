<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    /**
     * Display a listing of permissions grouped by category.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('view-permissions');

        $permissions = Permission::all();

        // Group permissions by category (extracted from permission name)
        // Format: "action-category" -> group by category
        $grouped = $permissions->groupBy(function ($permission) {
            // Extract category from permission name (everything after first dash)
            $parts = explode('-', $permission->name, 2);
            return isset($parts[1]) ? ucfirst($parts[1]) : 'Other';
        })->map(function ($group, $category) {
            return [
                'category' => $category,
                'permissions' => $group->map(function ($permission) {
                    // Extract action from permission name (everything before first dash)
                    $parts = explode('-', $permission->name, 2);
                    $action = $parts[0] ?? '';

                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'action' => ucfirst($action),
                        'guard_name' => $permission->guard_name,
                        'created_at' => $permission->created_at,
                    ];
                })->values(),
            ];
        })->values();

        $this->logActivity('api_call', 'read', 'Permission', null, 'Listed permissions');

        return response()->json([
            'success' => true,
            'data' => $grouped,
            'meta' => [
                'total_permissions' => $permissions->count(),
                'total_categories' => $grouped->count(),
            ],
        ]);
    }

    /**
     * Display a flat list of all permissions.
     */
    public function list(Request $request): JsonResponse
    {
        $this->authorize('view-permissions');

        $permissions = Permission::all()->map(function ($permission) {
            $parts = explode('-', $permission->name, 2);
            $action = $parts[0] ?? '';
            $category = isset($parts[1]) ? ucfirst($parts[1]) : 'Other';

            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'action' => ucfirst($action),
                'category' => $category,
                'guard_name' => $permission->guard_name,
                'created_at' => $permission->created_at,
            ];
        });

        $this->logActivity('api_call', 'read', 'Permission', null, 'Listed permissions (flat)');

        return response()->json([
            'success' => true,
            'data' => $permissions,
            'meta' => [
                'total' => $permissions->count(),
            ],
        ]);
    }

    /**
     * Display the specified permission.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $this->authorize('view-permissions');

        $permission = Permission::findOrFail($id);

        // Extract action and category
        $parts = explode('-', $permission->name, 2);
        $action = $parts[0] ?? '';
        $category = isset($parts[1]) ? ucfirst($parts[1]) : 'Other';

        $permissionData = [
            'id' => $permission->id,
            'name' => $permission->name,
            'action' => ucfirst($action),
            'category' => $category,
            'guard_name' => $permission->guard_name,
            'created_at' => $permission->created_at,
            'updated_at' => $permission->updated_at,
            'roles_count' => $permission->roles()->count(),
        ];

        $this->logActivity('api_call', 'read', 'Permission', $permission->id, "Viewed permission: {$permission->name}");

        return response()->json([
            'success' => true,
            'data' => $permissionData,
        ]);
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
