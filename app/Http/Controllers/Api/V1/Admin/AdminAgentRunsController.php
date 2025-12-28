<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentRun;
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
     *     @OA\Parameter(name="state", in="query", required=false, @OA\Schema(type="string", enum={"pending", "accepted", "running", "completed", "failed", "cancelled", "timeout"}), description="Filter by state"),
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
        $state = $request->query('state');
        $sort = $request->query('sort', 'started_at_desc');

        $query = AgentRun::query()
            ->with(['agent:id,name,slug'])
            ->select('agent_runs.*')
            ->selectRaw('EXTRACT(EPOCH FROM (finished_at - started_at)) * 1000 as duration_ms');

        // Search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('agent_runs.id', 'ILIKE', "%{$search}%")
                    ->orWhereHas('agent', function ($agentQuery) use ($search) {
                        $agentQuery->where('name', 'ILIKE', "%{$search}%");
                    });
            });
        }

        // State filter
        if ($state) {
            $query->where('state', $state);
        }

        // Sorting
        switch ($sort) {
            case 'started_at_asc':
                $query->orderBy('started_at', 'asc');
                break;
            case 'duration_desc':
                $query->orderByRaw('(finished_at - started_at) DESC NULLS LAST');
                break;
            case 'started_at_desc':
            default:
                $query->orderBy('started_at', 'desc');
                break;
        }

        $runs = $query->paginate($perPage);

        // Transform the data to match frontend expectations
        $runs->getCollection()->transform(function ($run) {
            return [
                'id' => $run->id,
                'agent_id' => $run->agent_id,
                'agent_name' => $run->agent?->name ?? 'Unknown Agent',
                'state' => $run->state,
                'input' => $run->input,
                'output' => $run->output,
                'error' => $run->error,
                'started_at' => $run->started_at?->toISOString(),
                'finished_at' => $run->finished_at?->toISOString(),
                'duration_ms' => $run->duration_ms ? (int) $run->duration_ms : null,
                'created_at' => $run->created_at?->toISOString(),
                'updated_at' => $run->updated_at?->toISOString(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $runs,
        ]);
    }

    /**
     * Get specific agent run details.
     *
     * @OA\Get(
     *     path="/v1/admin/agent-runs/{id}",
     *     summary="Get specific agent run details",
     *     description="Get detailed information about a specific agent execution run",
     *     operationId="adminGetAgentRun",
     *     tags={"Admin - Agent Runs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid"), description="Agent Run ID"),
     *     @OA\Response(
     *         response=200,
     *         description="Agent run details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Agent run not found")
     * )
     */
    public function show(string $id): JsonResponse
    {
        $run = AgentRun::with(['agent:id,name,slug,icon_url'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $run->id,
                'agent_id' => $run->agent_id,
                'agent' => $run->agent,
                'state' => $run->state,
                'input' => $run->input,
                'output' => $run->output,
                'error' => $run->error,
                'started_at' => $run->started_at?->toISOString(),
                'finished_at' => $run->finished_at?->toISOString(),
                'duration_ms' => $run->finished_at && $run->started_at
                    ? (int) ($run->finished_at->timestamp - $run->started_at->timestamp) * 1000
                    : null,
                'created_at' => $run->created_at?->toISOString(),
                'updated_at' => $run->updated_at?->toISOString(),
            ],
        ]);
    }
}
