<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\StoreProjectRequest;
use App\Http\Requests\Organizations\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\UserActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectController extends Controller
{
    /**
     * @OA\Get(
     *     path="/projects",
     *     summary="List projects",
     *     operationId="getProjects",
     *     tags={"Projects"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="organization_id",
     *         in="query",
     *         description="Filter by organization ID",
     *         required=false,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
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

    /**
     * @OA\Post(
     *     path="/projects",
     *     summary="Create project",
     *     operationId="createProject",
     *     tags={"Projects"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Project created successfully",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = Project::create($request->validated());

        $this->logActivity('create', 'create', 'Project', $project->id, "Project created: {$project->name}");

        return (new ProjectResource($project->load('organization', 'branch', 'department', 'projectManager')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @OA\Get(
     *     path="/projects/{project}",
     *     summary="Get project details",
     *     operationId="getProject",
     *     tags={"Projects"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="project",
     *         in="path",
     *         description="Project ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=404, description="Project not found")
     * )
     */
    public function show(Project $project): ProjectResource
    {
        $this->authorize('view', $project);

        $project->load(['organization', 'branch', 'department', 'projectManager'])
            ->loadCount('teams');

        $this->logActivity('api_call', 'read', 'Project', $project->id, "Viewed project: {$project->name}");

        return new ProjectResource($project);
    }

    /**
     * @OA\Put(
     *     path="/projects/{project}",
     *     summary="Update project",
     *     operationId="updateProject",
     *     tags={"Projects"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="project",
     *         in="path",
     *         description="Project ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Project updated successfully",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        $this->authorize('update', $project);

        $project->update($request->validated());

        $this->logActivity('update', 'update', 'Project', $project->id, "Project updated: {$project->name}");

        return new ProjectResource($project->load('organization', 'branch', 'department', 'projectManager'));
    }

    /**
     * @OA\Delete(
     *     path="/projects/{project}",
     *     summary="Delete project",
     *     operationId="deleteProject",
     *     tags={"Projects"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="project",
     *         in="path",
     *         description="Project ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Project deleted successfully"
     *     ),
     *     @OA\Response(response=404, description="Project not found")
     * )
     */
    public function destroy(Project $project): JsonResponse
    {
        $this->authorize('delete', $project);

        $name = $project->name;
        $project->delete();

        $this->logActivity('delete', 'delete', 'Project', $project->id, "Project deleted: {$name}");

        return response()->json(null, 204);
    }

    /**
     * @OA\Get(
     *     path="/projects/{project}/stats",
     *     summary="Get project stats",
     *     operationId="getProjectStats",
     *     tags={"Projects"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="project",
     *         in="path",
     *         description="Project ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="project", type="object"),
     *                 @OA\Property(property="teams_count", type="integer"),
     *                 @OA\Property(property="members_count", type="integer"),
     *                 @OA\Property(property="status", type="string"),
     *                 @OA\Property(property="progress", type="number")
     *             )
     *         )
     *     )
     * )
     */
    public function stats(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $this->logActivity('api_call', 'read', 'Project', $project->id, "Viewed project stats: {$project->name}");

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
