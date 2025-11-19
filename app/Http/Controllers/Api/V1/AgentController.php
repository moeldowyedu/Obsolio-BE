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
     * Display a listing of agents.
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
     * Store a newly created agent.
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
     * Display the specified agent.
     */
    public function show(Agent $agent): AgentResource
    {
        $this->authorize('view', $agent);

        $agent->load(['createdBy', 'marketplaceListing'])
            ->loadCount(['executions', 'jobFlows']);

        return new AgentResource($agent);
    }

    /**
     * Update the specified agent.
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
     * Remove the specified agent.
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
     * Execute the specified agent.
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
     * Clone the specified agent.
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
     * Publish the specified agent to marketplace.
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
