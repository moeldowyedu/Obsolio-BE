<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentExecution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Admin - Agent Runs",
 *     description="Admin endpoints for agent execution history"
 * )
 */
class AdminAgentRunsController extends Controller
{
    /**
     * List all agent executions.
     *
     * @OA\Get(
     *     path="/api/v1/admin/agent-runs",
     *     summary="List all agent executions",
     *     description="Get paginated list of all agent execution history with filters",
     *     operationId="adminListAgentRuns",
     *     tags={"Admin - Agent Runs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer"), description="Page number"),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer"), description="Items per page (max 100)"),
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string"), description="Search by agent name or run ID"),
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string", enum={"pending", "running", "completed", "failed"}), description="Filter by status"),
     *     @OA\Parameter(name="sort", in="query", required=false, @OA\Schema(type="string", enum={"started_at_desc", "started_at_asc", "duration_desc"}), description="Sort order"),
     *     @OA\Response(
     *         response=200,
     *         description="Agent runs retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="data", type="array", @OA\Items(
     *                     @OA\Property(property="id", type="string", format="uuid"),
     *                     @OA\Property(property="agent_id", type="string", format="uuid"),
     *                     @OA\Property(property="agent_name", type="string"),
     *                     @OA\Property(property="status", type="string"),
     *                     @OA\Property(property="input", type="object"),
     *                     @OA\Property(property="output", type="object"),
     *                     @OA\Property(property="error", type="string"),
     *                     @OA\Property(property="started_at", type="string", format="date-time"),
     *                     @OA\Property(property="completed_at", type="string", format="date-time"),
     *                     @OA\Property(property="duration_ms", type="integer"),
     *                     @OA\Property(property="triggered_by", type="object")
     *                 )),
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="last_page", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="total", type="integer")
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->query('per_page', 20), 100);
        $search = $request->query('search');
        $status = $request->query('status');
        $sort = $request->query('sort', 'started_at_desc');

        $query = AgentExecution::query()
            ->with(['agent:id,name,slug', 'user:id,name,email'])
            ->select('agent_executions.*')
            ->selectRaw('EXTRACT(EPOCH FROM (completed_at - started_at)) * 1000 as duration_ms');

        // Search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('agent_executions.id', 'ILIKE', "%{$search}%")
                    ->orWhereHas('agent', function ($agentQuery) use ($search) {
                        $agentQuery->where('name', 'ILIKE', "%{$search}%");
                    });
            });
        }

        // Status filter
        if ($status) {
            $query->where('status', $status);
        }

        // Sorting
        switch ($sort) {
            case 'started_at_asc':
                $query->orderBy('started_at', 'asc');
                break;
            case 'duration_desc':
                $query->orderByRaw('(completed_at - started_at) DESC NULLS LAST');
                break;
            case 'started_at_desc':
            default:
                $query->orderBy('started_at', 'desc');
                break;
        }

        $executions = $query->paginate($perPage);

        // Transform the data to match frontend expectations
        $executions->getCollection()->transform(function ($execution) {
            return [
                'id' => $execution->id,
                'agent_id' => $execution->agent_id,
                'agent_name' => $execution->agent?->name ?? 'Unknown Agent',
                'status' => $execution->status,
                'input' => $execution->input,
                'output' => $execution->output,
                'error' => $execution->error,
                'started_at' => $execution->started_at?->toISOString(),
                'completed_at' => $execution->completed_at?->toISOString(),
                'duration_ms' => $execution->duration_ms ? (int) $execution->duration_ms : null,
                'triggered_by' => $execution->user ? [
                    'id' => $execution->user->id,
                    'name' => $execution->user->name,
                    'email' => $execution->user->email,
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $executions,
        ]);
    }
}
