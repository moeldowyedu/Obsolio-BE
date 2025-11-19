<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $projects = Project::whereHas('organization', function ($query) {
                $query->where('tenant_id', tenant('id'));
            })
            ->with(['organization', 'branch', 'department', 'projectManager'])
            ->withCount('teams')
            ->when(request('organization_id'), function ($query, $orgId) {
                $query->where('organization_id', $orgId);
            })
            ->when(request('status'), function ($query, $status) {
                $query->where('status', $status);
            })
            ->paginate(request('per_page', 15));

        return ProjectResource::collection($projects);
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = Project::create($request->validated());

        activity()
            ->performedOn($project)
            ->causedBy(auth()->user())
            ->log('Project created');

        return (new ProjectResource($project->load('organization', 'branch', 'department', 'projectManager')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Project $project): ProjectResource
    {
        $this->authorize('view', $project);

        $project->load(['organization', 'branch', 'department', 'projectManager'])
            ->loadCount('teams');

        return new ProjectResource($project);
    }

    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        $this->authorize('update', $project);

        $project->update($request->validated());

        activity()
            ->performedOn($project)
            ->causedBy(auth()->user())
            ->log('Project updated');

        return new ProjectResource($project->load('organization', 'branch', 'department', 'projectManager'));
    }

    public function destroy(Project $project): JsonResponse
    {
        $this->authorize('delete', $project);

        activity()
            ->performedOn($project)
            ->causedBy(auth()->user())
            ->log('Project deleted');

        $project->delete();

        return response()->json(null, 204);
    }

    public function stats(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json([
            'data' => [
                'project' => new ProjectResource($project),
                'teams_count' => $project->teams()->count(),
                'members_count' => $project->teams()->withCount('members')->get()->sum('members_count'),
                'status' => $project->status,
                'progress' => $this->calculateProgress($project),
            ]
        ]);
    }

    private function calculateProgress(Project $project): float
    {
        if (!$project->start_date || !$project->end_date) {
            return 0.0;
        }

        $totalDays = $project->start_date->diffInDays($project->end_date);
        $elapsedDays = $project->start_date->diffInDays(now());

        if ($totalDays === 0) {
            return 100.0;
        }

        return min(100.0, max(0.0, ($elapsedDays / $totalDays) * 100));
    }
}
