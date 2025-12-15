<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkflowRequest;
use App\Http\Requests\UpdateWorkflowRequest;
use App\Http\Resources\WorkflowExecutionResource;
use App\Http\Resources\WorkflowResource;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkflowController extends Controller
{
    /**
     * Display a listing of workflows.
     */
    public function index(): AnonymousResourceCollection
    {
        $workflows = Workflow::where('tenant_id', tenant('id'))
            ->with(['createdBy', 'organization', 'department', 'project'])
            ->withCount(['executions'])
            ->paginate(request('per_page', 15));

        return WorkflowResource::collection($workflows);
    }

    /**
     * Store a newly created workflow.
     */
    public function store(StoreWorkflowRequest $request): JsonResponse
    {
        $workflow = Workflow::create([
            'tenant_id' => tenant('id'),
            'created_by_user_id' => auth()->id(),
            ...$request->validated(),
        ]);

        activity()
            ->performedOn($workflow)
            ->causedBy(auth()->user())
            ->log('Workflow created');

        $workflow->load(['createdBy', 'organization', 'department', 'project']);

        return (new WorkflowResource($workflow))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified workflow.
     */
    public function show(Workflow $workflow): WorkflowResource
    {
        $this->authorize('view', $workflow);

        $workflow->load(['createdBy', 'organization', 'department', 'project'])
            ->loadCount(['executions']);

        return new WorkflowResource($workflow);
    }

    /**
     * Update the specified workflow.
     */
    public function update(UpdateWorkflowRequest $request, Workflow $workflow): WorkflowResource
    {
        $this->authorize('update', $workflow);

        $workflow->update($request->validated());

        activity()
            ->performedOn($workflow)
            ->causedBy(auth()->user())
            ->log('Workflow updated');

        $workflow->load(['createdBy', 'organization', 'department', 'project']);

        return new WorkflowResource($workflow);
    }

    /**
     * Remove the specified workflow.
     */
    public function destroy(Workflow $workflow): JsonResponse
    {
        $this->authorize('delete', $workflow);

        activity()
            ->performedOn($workflow)
            ->causedBy(auth()->user())
            ->log('Workflow deleted');

        $workflow->delete();

        return response()->json(null, 204);
    }

    /**
     * Execute the specified workflow.
     */
    public function execute(Request $request, Workflow $workflow): JsonResponse
    {
        $this->authorize('execute', $workflow);

        $request->validate([
            'input_data' => 'nullable|array',
        ]);

        // Create workflow execution record
        $execution = WorkflowExecution::create([
            'tenant_id' => tenant('id'),
            'workflow_id' => $workflow->id,
            'triggered_by_user_id' => auth()->id(),
            'status' => 'queued',
            'input_data' => $request->input('input_data', []),
            'workflow_snapshot' => [
                'nodes' => $workflow->nodes,
                'edges' => $workflow->edges,
                'config' => $workflow->config,
            ],
        ]);

        activity()
            ->performedOn($execution)
            ->causedBy(auth()->user())
            ->log('Workflow execution started');

        // TODO: Dispatch job to execute workflow asynchronously
        // dispatch(new ExecuteWorkflowJob($execution));

        return (new WorkflowExecutionResource($execution))
            ->response()
            ->setStatusCode(201);
    }
}
