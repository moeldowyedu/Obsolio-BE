<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTeamRequest;
use App\Http\Requests\UpdateTeamRequest;
use App\Http\Requests\AddTeamMemberRequest;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TeamController extends Controller
{
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

    public function store(StoreTeamRequest $request): JsonResponse
    {
        $team = Team::create($request->validated());

        activity()
            ->performedOn($team)
            ->causedBy(auth()->user())
            ->log('Team created');

        return (new TeamResource($team->load('organization', 'department', 'project', 'teamLead')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Team $team): TeamResource
    {
        $this->authorize('view', $team);

        $team->load(['organization', 'department', 'project', 'teamLead', 'members'])
            ->loadCount('members');

        return new TeamResource($team);
    }

    public function update(UpdateTeamRequest $request, Team $team): TeamResource
    {
        $this->authorize('update', $team);

        $team->update($request->validated());

        activity()
            ->performedOn($team)
            ->causedBy(auth()->user())
            ->log('Team updated');

        return new TeamResource($team->load('organization', 'department', 'project', 'teamLead'));
    }

    public function destroy(Team $team): JsonResponse
    {
        $this->authorize('delete', $team);

        activity()
            ->performedOn($team)
            ->causedBy(auth()->user())
            ->log('Team deleted');

        $team->delete();

        return response()->json(null, 204);
    }

    public function addMember(AddTeamMemberRequest $request, Team $team): JsonResponse
    {
        $this->authorize('update', $team);

        $member = TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $request->user_id,
            'role' => $request->role,
        ]);

        activity()
            ->performedOn($team)
            ->causedBy(auth()->user())
            ->withProperties(['user_id' => $request->user_id, 'role' => $request->role])
            ->log('Team member added');

        return response()->json([
            'message' => 'Team member added successfully',
            'data' => $member
        ], 201);
    }

    public function removeMember(Team $team, string $userId): JsonResponse
    {
        $this->authorize('update', $team);

        TeamMember::where('team_id', $team->id)
            ->where('user_id', $userId)
            ->delete();

        activity()
            ->performedOn($team)
            ->causedBy(auth()->user())
            ->withProperties(['user_id' => $userId])
            ->log('Team member removed');

        return response()->json(null, 204);
    }
}
