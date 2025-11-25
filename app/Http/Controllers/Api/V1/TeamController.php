<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\StoreTeamRequest;
use App\Http\Requests\Organizations\UpdateTeamRequest;
use App\Http\Requests\AddTeamMemberRequest;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\UserActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TeamController extends Controller
{
    /**
     * @OA\Get(
     *     path="/teams",
     *     summary="List teams",
     *     operationId="getTeams",
     *     tags={"Teams"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="organization_id",
     *         in="query",
     *         description="Filter by organization ID",
     *         required=false,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="project_id",
     *         in="query",
     *         description="Filter by project ID",
     *         required=false,
     *         @OA\Schema(type="string", format="uuid")
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
        $teams = Team::whereHas('organization', function ($query) {
            $query->where('tenant_id', tenant('id'));
        })
            ->with(['organization', 'department', 'project', 'teamLead'])
            ->withCount('members')
            ->when(request('organization_id'), function ($query, $orgId) {
                $query->where('organization_id', $orgId);
            })
            ->when(request('project_id'), function ($query, $projectId) {
                $query->where('project_id', $projectId);
            })
            ->paginate(request('per_page', 15));

        return TeamResource::collection($teams);
    }

    /**
     * @OA\Post(
     *     path="/teams",
     *     summary="Create team",
     *     operationId="createTeam",
     *     tags={"Teams"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Team created successfully",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(StoreTeamRequest $request): JsonResponse
    {
        $team = Team::create($request->validated());

        $this->logActivity('create', 'create', 'Team', $team->id, "Team created: {$team->name}");

        return (new TeamResource($team->load('organization', 'department', 'project', 'teamLead')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @OA\Get(
     *     path="/teams/{team}",
     *     summary="Get team details",
     *     operationId="getTeam",
     *     tags={"Teams"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="team",
     *         in="path",
     *         description="Team ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=404, description="Team not found")
     * )
     */
    public function show(Team $team): TeamResource
    {
        $this->authorize('view', $team);

        $team->load(['organization', 'department', 'project', 'teamLead', 'members'])
            ->loadCount('members');

        $this->logActivity('api_call', 'read', 'Team', $team->id, "Viewed team: {$team->name}");

        return new TeamResource($team);
    }

    /**
     * @OA\Put(
     *     path="/teams/{team}",
     *     summary="Update team",
     *     operationId="updateTeam",
     *     tags={"Teams"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="team",
     *         in="path",
     *         description="Team ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Team updated successfully",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(UpdateTeamRequest $request, Team $team): TeamResource
    {
        $this->authorize('update', $team);

        $team->update($request->validated());

        $this->logActivity('update', 'update', 'Team', $team->id, "Team updated: {$team->name}");

        return new TeamResource($team->load('organization', 'department', 'project', 'teamLead'));
    }

    /**
     * @OA\Delete(
     *     path="/teams/{team}",
     *     summary="Delete team",
     *     operationId="deleteTeam",
     *     tags={"Teams"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="team",
     *         in="path",
     *         description="Team ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Team deleted successfully"
     *     ),
     *     @OA\Response(response=404, description="Team not found")
     * )
     */
    public function destroy(Team $team): JsonResponse
    {
        $this->authorize('delete', $team);

        $name = $team->name;
        $team->delete();

        $this->logActivity('delete', 'delete', 'Team', $team->id, "Team deleted: {$name}");

        return response()->json(null, 204);
    }

    /**
     * @OA\Post(
     *     path="/teams/{team}/members",
     *     summary="Add team member",
     *     operationId="addTeamMember",
     *     tags={"Teams"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="team",
     *         in="path",
     *         description="Team ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Member added successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function addMember(AddTeamMemberRequest $request, Team $team): JsonResponse
    {
        $this->authorize('update', $team);

        $member = TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $request->user_id,
            'role' => $request->role,
        ]);

        $this->logActivity('update', 'create', 'TeamMember', $member->id, "Team member added to: {$team->name}");

        return response()->json([
            'message' => 'Team member added successfully',
            'data' => $member
        ], 201);
    }

    /**
     * @OA\Delete(
     *     path="/teams/{team}/members/{userId}",
     *     summary="Remove team member",
     *     operationId="removeTeamMember",
     *     tags={"Teams"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="team",
     *         in="path",
     *         description="Team ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="userId",
     *         in="path",
     *         description="User ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Member removed successfully"
     *     )
     * )
     */
    public function removeMember(Team $team, string $userId): JsonResponse
    {
        $this->authorize('update', $team);

        TeamMember::where('team_id', $team->id)
            ->where('user_id', $userId)
            ->delete();

        $this->logActivity('update', 'delete', 'TeamMember', null, "Team member removed from: {$team->name}");

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
