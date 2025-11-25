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
    /**
     * @OA\Get(
     *     path="/job-flows",
     *     summary="List job flows",
     *     operationId="getJobFlows",
     *     tags={"Job Flows"},
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
     *     )
     * )
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
    /**
     * @OA\Post(
     *     path="/job-flows",
     *     summary="Create job flow",
     *     operationId="createJobFlow",
     *     tags={"Job Flows"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Job flow created successfully",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
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
    /**
     * @OA\Get(
     *     path="/job-flows/{jobFlow}",
     *     summary="Get job flow details",
     *     operationId="getJobFlow",
     *     tags={"Job Flows"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="jobFlow",
     *         in="path",
     *         description="Job Flow ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=404, description="Job flow not found")
     * )
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
    /**
     * @OA\Put(
     *     path="/job-flows/{jobFlow}",
     *     summary="Update job flow",
     *     operationId="updateJobFlow",
     *     tags={"Job Flows"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="jobFlow",
     *         in="path",
     *         description="Job Flow ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Job flow updated successfully",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
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
    /**
     * @OA\Delete(
     *     path="/job-flows/{jobFlow}",
     *     summary="Delete job flow",
     *     operationId="deleteJobFlow",
     *     tags={"Job Flows"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="jobFlow",
     *         in="path",
     *         description="Job Flow ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Job flow deleted successfully"
     *     ),
     *     @OA\Response(response=404, description="Job flow not found")
     * )
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
    /**
     * @OA\Post(
     *     path="/job-flows/{jobFlow}/trigger",
     *     summary="Trigger job flow",
     *     operationId="triggerJobFlow",
     *     tags={"Job Flows"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="jobFlow",
     *         in="path",
     *         description="Job Flow ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Job flow started successfully",
     *         @OA\JsonContent(type="object")
     *     )
     * )
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
    /**
     * @OA\Put(
     *     path="/job-flows/{jobFlow}/status",
     *     summary="Update flow status",
     *     operationId="updateJobFlowStatus",
     *     tags={"Job Flows"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="jobFlow",
     *         in="path",
     *         description="Job Flow ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", enum={"active","paused"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Status updated successfully",
     *         @OA\JsonContent(type="object")
     *     )
     * )
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
