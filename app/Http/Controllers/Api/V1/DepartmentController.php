<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\StoreDepartmentRequest;
use App\Http\Requests\Organizations\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use App\Models\UserActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DepartmentController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $departments = Department::whereHas('organization', function ($query) {
                $query->where('tenant_id', tenant('id'));
            })
            ->with(['organization', 'branch', 'head', 'parentDepartment'])
            ->when(request('organization_id'), function ($query, $orgId) {
                $query->where('organization_id', $orgId);
            })
            ->when(request('branch_id'), function ($query, $branchId) {
                $query->where('branch_id', $branchId);
            })
            ->paginate(request('per_page', 15));

        return DepartmentResource::collection($departments);
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $department = Department::create($request->validated());

        $this->logActivity('create', 'create', 'Department', $department->id, "Department created: {$department->name}");

        return (new DepartmentResource($department->load('organization', 'branch', 'departmentHead')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Department $department): DepartmentResource
    {
        $this->authorize('view', $department);

        $department->load(['organization', 'branch', 'departmentHead', 'parentDepartment', 'subDepartments']);

        $this->logActivity('api_call', 'read', 'Department', $department->id, "Viewed department: {$department->name}");

        return new DepartmentResource($department);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): DepartmentResource
    {
        $this->authorize('update', $department);

        $department->update($request->validated());

        $this->logActivity('update', 'update', 'Department', $department->id, "Department updated: {$department->name}");

        return new DepartmentResource($department->load('organization', 'branch', 'departmentHead'));
    }

    public function destroy(Department $department): JsonResponse
    {
        $this->authorize('delete', $department);

        // Check if department has sub-departments
        if ($department->subDepartments()->exists()) {
            return response()->json([
                'message' => 'Cannot delete department with sub-departments.'
            ], 422);
        }

        $name = $department->name;
        $department->delete();

        $this->logActivity('delete', 'delete', 'Department', $department->id, "Department deleted: {$name}");

        return response()->json(null, 204);
    }

    public function hierarchy(): JsonResponse
    {
        $departments = Department::whereHas('organization', function ($query) {
                $query->where('tenant_id', tenant('id'));
            })
            ->whereNull('parent_department_id')
            ->with(['subDepartments' => function ($query) {
                $query->with('subDepartments');
            }])
            ->get();

        $this->logActivity('api_call', 'read', 'Department', null, 'Viewed department hierarchy');

        return response()->json([
            'data' => DepartmentResource::collection($departments)
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
