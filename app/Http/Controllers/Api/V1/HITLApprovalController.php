<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveHITLRequest;
use App\Http\Requests\RejectHITLRequest;
use App\Http\Resources\HITLApprovalResource;
use App\Models\HITLApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HITLApprovalController extends Controller
{
    /**
     * Display a listing of HITL approvals.
     */
    public function index(): AnonymousResourceCollection
    {
        $query = HITLApproval::where('tenant_id', tenant('id'))
            ->with(['jobFlow', 'agent', 'assignedTo', 'reviewedBy']);

        // Filter by status if provided
        if (request('status')) {
            $query->where('status', request('status'));
        }

        // Filter by assigned user if provided
        if (request('assigned_to')) {
            $query->where('assigned_to_user_id', request('assigned_to'));
        }

        // Filter by priority if provided
        if (request('priority')) {
            $query->where('priority', request('priority'));
        }

        $approvals = $query
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(request('per_page', 15));

        return HITLApprovalResource::collection($approvals);
    }

    /**
     * Display the specified HITL approval.
     */
    public function show(HITLApproval $hitlApproval): HITLApprovalResource
    {
        $this->authorize('view', $hitlApproval);

        $hitlApproval->load(['jobFlow', 'agent', 'assignedTo', 'reviewedBy']);

        return new HITLApprovalResource($hitlApproval);
    }

    /**
     * Approve the specified HITL approval.
     */
    public function approve(ApproveHITLRequest $request, HITLApproval $hitlApproval): JsonResponse
    {
        $this->authorize('review', $hitlApproval);

        $hitlApproval->update([
            'status' => 'approved',
            'reviewed_by_user_id' => auth()->id(),
            'reviewed_at' => now(),
            'reviewer_comments' => $request->validated('comments'),
        ]);

        activity()
            ->performedOn($hitlApproval)
            ->causedBy(auth()->user())
            ->log('HITL approval approved');

        // TODO: Trigger continuation of the job/workflow
        // dispatch(new ContinueExecutionJob($hitlApproval));

        $hitlApproval->load(['jobFlow', 'agent', 'assignedTo', 'reviewedBy']);

        return (new HITLApprovalResource($hitlApproval))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Reject the specified HITL approval.
     */
    public function reject(RejectHITLRequest $request, HITLApproval $hitlApproval): JsonResponse
    {
        $this->authorize('review', $hitlApproval);

        $hitlApproval->update([
            'status' => 'rejected',
            'reviewed_by_user_id' => auth()->id(),
            'reviewed_at' => now(),
            'reviewer_comments' => $request->validated('comments'),
        ]);

        activity()
            ->performedOn($hitlApproval)
            ->causedBy(auth()->user())
            ->log('HITL approval rejected');

        // TODO: Handle rejection - may need to stop or rollback execution
        // dispatch(new HandleRejectedApprovalJob($hitlApproval));

        $hitlApproval->load(['jobFlow', 'agent', 'assignedTo', 'reviewedBy']);

        return (new HITLApprovalResource($hitlApproval))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Escalate the specified HITL approval.
     */
    public function escalate(HITLApproval $hitlApproval): JsonResponse
    {
        $this->authorize('review', $hitlApproval);

        $hitlApproval->update([
            'status' => 'escalated',
            'priority' => 'high',
        ]);

        activity()
            ->performedOn($hitlApproval)
            ->causedBy(auth()->user())
            ->log('HITL approval escalated');

        // TODO: Notify supervisors or higher-level approvers
        // dispatch(new NotifyEscalationJob($hitlApproval));

        $hitlApproval->load(['jobFlow', 'agent', 'assignedTo', 'reviewedBy']);

        return (new HITLApprovalResource($hitlApproval))
            ->response()
            ->setStatusCode(200);
    }
}
