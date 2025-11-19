<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
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

        activity()
            ->performedOn($department)
            ->causedBy(auth()->user())
            ->log('Department created');

        return (new DepartmentResource($department->load('organization', 'branch', 'head')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Department $department): DepartmentResource
    {
        $this->authorize('view', $department);

        $department->load(['organization', 'branch', 'head', 'parentDepartment', 'subDepartments']);

        return new DepartmentResource($department);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): DepartmentResource
    {
        $this->authorize('update', $department);

        $department->update($request->validated());

        activity()
            ->performedOn($department)
            ->causedBy(auth()->user())
            ->log('Department updated');

        return new DepartmentResource($department->load('organization', 'branch', 'head'));
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

        activity()
            ->performedOn($department)
            ->causedBy(auth()->user())
            ->log('Department deleted');

        $department->delete();

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

        return response()->json([
            'data' => DepartmentResource::collection($departments)
        ]);
    }
}
