<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExecuteAgentRequest;
use App\Http\Requests\StoreAgentRequest;
use App\Http\Requests\UpdateAgentRequest;
use App\Http\Resources\AgentExecutionResource;
use App\Http\Resources\AgentResource;
use App\Models\Agent;
use App\Models\AgentExecution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class AgentController extends Controller
{
    /**
     * @OA\Get(
     *     path="/agents",
     *     summary="List all agents",
     *     description="Get a paginated list of all agents",
     *     operationId="getAgents",
     *     tags={"Agents"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(): AnonymousResourceCollection
    {
        $agents = Agent::where('tenant_id', tenant('id'))
            ->with(['createdBy'])
            ->withCount(['executions', 'jobFlows'])
            ->paginate(request('per_page', 15));

        return AgentResource::collection($agents);
    }

    /**
     * @OA\Post(
     *     path="/agents",
     *     summary="Create new agent",
     *     operationId="createAgent",
     *     tags={"Agents"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Agent created successfully",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(StoreAgentRequest $request): JsonResponse
    {
        $agent = Agent::create([
            'tenant_id' => tenant('id'),
            'created_by_user_id' => auth()->id(),
            ...$request->validated(),
        ]);

        activity()
            ->performedOn($agent)
            ->causedBy(auth()->user())
            ->log('Agent created');

        $agent->load(['createdBy']);

        return (new AgentResource($agent))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @OA\Get(
     *     path="/agents/{agent}",
     *     summary="Get agent details",
     *     operationId="getAgent",
     *     tags={"Agents"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="agent",
     *         in="path",
     *         description="Agent ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=404, description="Agent not found")
     * )
     */
    public function show(Agent $agent): AgentResource
    {
        $this->authorize('view', $agent);

        $agent->load(['createdBy', 'marketplaceListing'])
            ->loadCount(['executions', 'jobFlows']);

        return new AgentResource($agent);
    }

    /**
     * @OA\Put(
     *     path="/agents/{agent}",
     *     summary="Update agent",
     *     operationId="updateAgent",
     *     tags={"Agents"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="agent",
     *         in="path",
     *         description="Agent ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Agent updated successfully",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(UpdateAgentRequest $request, Agent $agent): AgentResource
    {
        $this->authorize('update', $agent);

        $agent->update($request->validated());

        activity()
            ->performedOn($agent)
            ->causedBy(auth()->user())
            ->log('Agent updated');

        $agent->load(['createdBy']);

        return new AgentResource($agent);
    }

    /**
     * @OA\Delete(
     *     path="/agents/{agent}",
     *     summary="Delete agent",
     *     operationId="deleteAgent",
     *     tags={"Agents"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="agent",
     *         in="path",
     *         description="Agent ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Agent deleted successfully"
     *     ),
     *     @OA\Response(response=404, description="Agent not found")
     * )
     */
    public function destroy(Agent $agent): JsonResponse
    {
        $this->authorize('delete', $agent);

        activity()
            ->performedOn($agent)
            ->causedBy(auth()->user())
            ->log('Agent deleted');

        $agent->delete();

        return response()->json(null, 204);
    }

    /**
     * @OA\Post(
     *     path="/agents/{agent}/execute",
     *     summary="Execute agent",
     *     operationId="executeAgent",
     *     tags={"Agents"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="agent",
     *         in="path",
     *         description="Agent ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Execution started",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function execute(ExecuteAgentRequest $request, Agent $agent): JsonResponse
    {
        $this->authorize('execute', $agent);

        // Create execution record
        $execution = AgentExecution::create([
            'tenant_id' => tenant('id'),
            'agent_id' => $agent->id,
            'triggered_by_user_id' => auth()->id(),
            'status' => 'queued',
            'input_data' => $request->validated('input_data'),
            'config_snapshot' => $agent->config,
        ]);

        activity()
            ->performedOn($execution)
            ->causedBy(auth()->user())
            ->log('Agent execution started');

        // TODO: Dispatch job to execute agent asynchronously
        // dispatch(new ExecuteAgentJob($execution));

        return (new AgentExecutionResource($execution))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @OA\Post(
     *     path="/agents/{agent}/clone",
     *     summary="Clone agent",
     *     operationId="cloneAgent",
     *     tags={"Agents"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="agent",
     *         in="path",
     *         description="Agent ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Agent cloned successfully",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function clone(Agent $agent): JsonResponse
    {
        $this->authorize('view', $agent);

        $clonedAgent = $agent->replicate();
        $clonedAgent->name = $agent->name . ' (Copy)';
        $clonedAgent->created_by_user_id = auth()->id();
        $clonedAgent->is_published = false;
        $clonedAgent->marketplace_listing_id = null;
        $clonedAgent->version = 1;
        $clonedAgent->save();

        activity()
            ->performedOn($clonedAgent)
            ->causedBy(auth()->user())
            ->withProperties(['original_agent_id' => $agent->id])
            ->log('Agent cloned');

        $clonedAgent->load(['createdBy']);

        return (new AgentResource($clonedAgent))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @OA\Post(
     *     path="/agents/{agent}/publish",
     *     summary="Publish agent to marketplace",
     *     operationId="publishAgent",
     *     tags={"Agents"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="agent",
     *         in="path",
     *         description="Agent ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Agent published successfully",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function publish(Agent $agent): AgentResource
    {
        $this->authorize('update', $agent);

        $agent->update([
            'is_published' => true,
            'status' => 'active',
        ]);

        activity()
            ->performedOn($agent)
            ->causedBy(auth()->user())
            ->log('Agent published to marketplace');

        $agent->load(['createdBy', 'marketplaceListing']);

        return new AgentResource($agent);
    }
}
