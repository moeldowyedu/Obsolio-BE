<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BranchController extends Controller
{
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

    public function store(StoreBranchRequest $request): JsonResponse
    {
        $branch = Branch::create($request->validated());

        activity()
            ->performedOn($branch)
            ->causedBy(auth()->user())
            ->log('Branch created');

        return (new BranchResource($branch->load('organization', 'manager')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Branch $branch): BranchResource
    {
        $this->authorize('view', $branch);

        $branch->load(['organization', 'manager'])
            ->loadCount('departments');

        return new BranchResource($branch);
    }

    public function update(UpdateBranchRequest $request, Branch $branch): BranchResource
    {
        $this->authorize('update', $branch);

        $branch->update($request->validated());

        activity()
            ->performedOn($branch)
            ->causedBy(auth()->user())
            ->log('Branch updated');

        return new BranchResource($branch->load('organization', 'manager'));
    }

    public function destroy(Branch $branch): JsonResponse
    {
        $this->authorize('delete', $branch);

        activity()
            ->performedOn($branch)
            ->causedBy(auth()->user())
            ->log('Branch deleted');

        $branch->delete();

        return response()->json(null, 204);
    }
}
