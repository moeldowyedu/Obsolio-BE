<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJobFlowRequest;
use App\Http\Requests\UpdateJobFlowRequest;
use App\Http\Resources\JobFlowResource;
use App\Models\JobFlow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class JobFlowController extends Controller
{
    /**
     * Display a listing of job flows.
     */
    public function index(): AnonymousResourceCollection
    {
        $jobFlows = JobFlow::where('tenant_id', tenant('id'))
            ->with(['agent', 'organization', 'branch', 'department', 'project', 'reportingManager'])
            ->withCount(['executions', 'hitlApprovals'])
            ->paginate(request('per_page', 15));

        return JobFlowResource::collection($jobFlows);
    }

    /**
     * Store a newly created job flow.
     */
    public function store(StoreJobFlowRequest $request): JsonResponse
    {
        $jobFlow = JobFlow::create([
            'tenant_id' => tenant('id'),
            ...$request->validated(),
        ]);

        activity()
            ->performedOn($jobFlow)
            ->causedBy(auth()->user())
            ->log('Job flow created');

        $jobFlow->load(['agent', 'organization', 'branch', 'department', 'project']);

        return (new JobFlowResource($jobFlow))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified job flow.
     */
    public function show(JobFlow $jobFlow): JobFlowResource
    {
        $this->authorize('view', $jobFlow);

        $jobFlow->load([
            'agent',
            'organization',
            'branch',
            'department',
            'project',
            'reportingManager',
            'hitlSupervisor',
        ])->loadCount(['executions', 'hitlApprovals']);

        return new JobFlowResource($jobFlow);
    }

    /**
     * Update the specified job flow.
     */
    public function update(UpdateJobFlowRequest $request, JobFlow $jobFlow): JobFlowResource
    {
        $this->authorize('update', $jobFlow);

        $jobFlow->update($request->validated());

        activity()
            ->performedOn($jobFlow)
            ->causedBy(auth()->user())
            ->log('Job flow updated');

        $jobFlow->load(['agent', 'organization', 'branch', 'department', 'project']);

        return new JobFlowResource($jobFlow);
    }

    /**
     * Remove the specified job flow.
     */
    public function destroy(JobFlow $jobFlow): JsonResponse
    {
        $this->authorize('delete', $jobFlow);

        activity()
            ->performedOn($jobFlow)
            ->causedBy(auth()->user())
            ->log('Job flow deleted');

        $jobFlow->delete();

        return response()->json(null, 204);
    }

    /**
     * Run the specified job flow immediately.
     */
    public function run(JobFlow $jobFlow): JsonResponse
    {
        $this->authorize('update', $jobFlow);

        // Update job flow status to active
        $jobFlow->update([
            'status' => 'active',
            'last_run_at' => now(),
        ]);

        activity()
            ->performedOn($jobFlow)
            ->causedBy(auth()->user())
            ->log('Job flow started');

        // TODO: Dispatch job to execute the job flow
        // dispatch(new RunJobFlowJob($jobFlow));

        $jobFlow->load(['agent', 'organization']);

        return (new JobFlowResource($jobFlow))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Pause the specified job flow.
     */
    public function pause(JobFlow $jobFlow): JsonResponse
    {
        $this->authorize('update', $jobFlow);

        $jobFlow->update([
            'status' => 'paused',
        ]);

        activity()
            ->performedOn($jobFlow)
            ->causedBy(auth()->user())
            ->log('Job flow paused');

        $jobFlow->load(['agent', 'organization']);

        return (new JobFlowResource($jobFlow))
            ->response()
            ->setStatusCode(200);
    }
}
