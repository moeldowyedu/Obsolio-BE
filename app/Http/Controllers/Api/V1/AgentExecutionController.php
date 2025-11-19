<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AgentExecutionResource;
use App\Models\AgentExecution;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AgentExecutionController extends Controller
{
    /**
     * Display a listing of agent executions.
     * Read-only controller - executions are created through AgentController::execute
     */
    public function index(): AnonymousResourceCollection
    {
        $query = AgentExecution::where('tenant_id', tenant('id'))
            ->with(['agent', 'jobFlow', 'workflowExecution', 'triggeredByUser']);

        // Filter by agent if provided
        if (request('agent_id')) {
            $query->where('agent_id', request('agent_id'));
        }

        // Filter by job flow if provided
        if (request('job_flow_id')) {
            $query->where('job_flow_id', request('job_flow_id'));
        }

        // Filter by status if provided
        if (request('status')) {
            $query->where('status', request('status'));
        }

        // Filter by triggered_by if provided
        if (request('triggered_by')) {
            $query->where('triggered_by', request('triggered_by'));
        }

        $executions = $query
            ->orderBy('created_at', 'desc')
            ->paginate(request('per_page', 15));

        return AgentExecutionResource::collection($executions);
    }

    /**
     * Display the specified agent execution.
     */
    public function show(AgentExecution $agentExecution): AgentExecutionResource
    {
        $this->authorize('view', $agentExecution);

        $agentExecution->load([
            'agent',
            'jobFlow',
            'workflowExecution',
            'triggeredByUser',
        ]);

        return new AgentExecutionResource($agentExecution);
    }
}
