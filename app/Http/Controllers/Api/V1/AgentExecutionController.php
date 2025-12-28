<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentEndpoint;
use App\Models\AgentRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AgentExecutionController extends Controller
{
    /**
     * Get list of agent runs for the current tenant.
     *
     * @OA\Get(
     *     path="/v1/tenant/agent-runs",
     *     summary="Get list of agent execution runs",
     *     description="Get paginated list of agent execution history for the current tenant with optional filters",
     *     operationId="getTenantAgentRuns",
     *     tags={"Tenant - Agent Runs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="agent_id", in="query", required=false, @OA\Schema(type="string", format="uuid"), description="Filter by agent ID"),
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string", enum={"pending", "running", "completed", "failed"}), description="Filter by status"),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer"), description="Page number"),
     *     @OA\Response(
     *         response=200,
     *         description="Agent runs retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $agentId = $request->query('agent_id');
        $status = $request->query('status');

        $runs = AgentRun::query()
            ->when($agentId, function ($query, $agentId) {
                return $query->where('agent_id', $agentId);
            })
            ->when($status, function ($query, $status) {
                return $query->where('status', $status);
            })
            ->with('agent:id,name,slug')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $runs,
        ]);
    }

    /**
     * Execute an agent asynchronously.
     *
     * @OA\Post(
     *     path="/v1/tenant/agents/{id}/run",
     *     summary="Execute an agent asynchronously",
     *     description="Initiates asynchronous execution of an agent. The agent will process the request in the background and send results to the callback webhook.",
     *     operationId="executeAgent",
     *     tags={"Tenant - Agents"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Agent UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"input"},
     *             @OA\Property(
     *                 property="input",
     *                 type="object",
     *                 description="Input parameters for the agent execution",
     *                 example={"query": "What is the weather today?", "location": "Cairo"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=202,
     *         description="Agent execution initiated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Agent execution initiated"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="run_id", type="string", format="uuid", example="660e8400-e29b-41d4-a716-446655440001"),
     *                 @OA\Property(property="status", type="string", example="running"),
     *                 @OA\Property(
     *                     property="agent",
     *                     type="object",
     *                     @OA\Property(property="id", type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000"),
     *                     @OA\Property(property="name", type="string", example="Weather Agent"),
     *                     @OA\Property(property="runtime_type", type="string", example="n8n")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Agent is not active or no trigger endpoint configured",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Agent is not active")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Agent not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Agent not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Agent failed to accept execution or internal error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to connect to agent")
     *         )
     *     )
     * )
     */
    public function run(string $id, Request $request): JsonResponse
    {
        try {
            // Validate input
            $validated = $request->validate([
                'input' => 'required|array',
            ]);

            // Find the agent
            $agent = Agent::findOrFail($id);

            // Check if agent is active
            if (!$agent->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Agent is not active',
                ], 400);
            }

            // Get the trigger endpoint
            $triggerEndpoint = AgentEndpoint::where('agent_id', $agent->id)
                ->where('type', 'trigger')
                ->where('is_active', true)
                ->first();

            if (!$triggerEndpoint) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active trigger endpoint configured for this agent',
                ], 400);
            }

            // Create a new agent run
            $run = AgentRun::create([
                'agent_id' => $agent->id,
                'status' => 'pending',
                'input' => $validated['input'],
            ]);

            // Send request to agent trigger webhook
            try {
                $response = Http::timeout($agent->execution_timeout_ms / 1000)
                    ->withHeaders([
                        'X-Agent-Secret' => $triggerEndpoint->secret,
                        'X-Run-Id' => $run->id,
                    ])
                    ->post($triggerEndpoint->url, [
                        'run_id' => $run->id,
                        'input' => $validated['input'],
                    ]);

                // Check if request was accepted
                if ($response->successful()) {
                    // Update run status to running
                    $run->markAsRunning();

                    return response()->json([
                        'success' => true,
                        'message' => 'Agent execution initiated',
                        'data' => [
                            'run_id' => $run->id,
                            'status' => 'running',
                            'agent' => [
                                'id' => $agent->id,
                                'name' => $agent->name,
                                'runtime_type' => $agent->runtime_type,
                            ],
                        ],
                    ], 202); // 202 Accepted
                } else {
                    // Mark run as failed
                    $run->markAsFailed('Agent trigger endpoint returned error: ' . $response->status());

                    return response()->json([
                        'success' => false,
                        'message' => 'Agent failed to accept execution request',
                        'data' => [
                            'run_id' => $run->id,
                            'status' => 'failed',
                        ],
                    ], 500);
                }
            } catch (\Exception $e) {
                // Mark run as failed
                $run->markAsFailed('Failed to connect to agent trigger endpoint: ' . $e->getMessage());

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to connect to agent',
                    'data' => [
                        'run_id' => $run->id,
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                    ],
                ], 500);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Agent not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Agent execution error: ' . $e->getMessage(), [
                'agent_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get agent run status.
     *
     * @OA\Get(
     *     path="/v1/tenant/agent-runs/{run_id}",
     *     summary="Get agent execution status",
     *     description="Retrieve the status and results of an agent execution run",
     *     operationId="getAgentRunStatus",
     *     tags={"Tenant - Agent Runs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="run_id",
     *         in="path",
     *         description="Agent Run UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid", example="660e8400-e29b-41d4-a716-446655440001")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Run status retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="run_id", type="string", format="uuid", example="660e8400-e29b-41d4-a716-446655440001"),
     *                 @OA\Property(property="status", type="string", enum={"pending", "running", "completed", "failed"}, example="completed"),
     *                 @OA\Property(
     *                     property="input",
     *                     type="object",
     *                     example={"query": "What is the weather today?", "location": "Cairo"}
     *                 ),
     *                 @OA\Property(
     *                     property="output",
     *                     type="object",
     *                     nullable=true,
     *                     example={"result": "The weather in Cairo is sunny, 28°C"}
     *                 ),
     *                 @OA\Property(property="error", type="string", nullable=true, example=null),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-27T10:00:00.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-27T10:00:05.000000Z"),
     *                 @OA\Property(
     *                     property="agent",
     *                     type="object",
     *                     @OA\Property(property="id", type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000"),
     *                     @OA\Property(property="name", type="string", example="Weather Agent"),
     *                     @OA\Property(property="runtime_type", type="string", example="n8n")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Agent run not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Agent run not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Internal server error")
     *         )
     *     )
     * )
     */
    public function getRunStatus(string $runId): JsonResponse
    {
        try {
            $run = AgentRun::with('agent:id,name,runtime_type')->findOrFail($runId);

            return response()->json([
                'success' => true,
                'data' => [
                    'run_id' => $run->id,
                    'status' => $run->status,
                    'input' => $run->input,
                    'output' => $run->output,
                    'error' => $run->error,
                    'created_at' => $run->created_at,
                    'updated_at' => $run->updated_at,
                    'agent' => [
                        'id' => $run->agent->id,
                        'name' => $run->agent->name,
                        'runtime_type' => $run->agent->runtime_type,
                    ],
                ],
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Agent run not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Get run status error: ' . $e->getMessage(), [
                'run_id' => $runId,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Webhook callback for agent execution results.
     *
     * @OA\Post(
     *     path="/v1/webhooks/agents/callback",
     *     summary="Agent execution callback webhook",
     *     description="Webhook endpoint for agents to send execution results. This endpoint does not require JWT authentication but validates a secret token instead.",
     *     operationId="agentExecutionCallback",
     *     tags={"Webhooks"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"run_id", "status", "secret"},
     *             @OA\Property(property="run_id", type="string", format="uuid", example="660e8400-e29b-41d4-a716-446655440001"),
     *             @OA\Property(property="status", type="string", enum={"completed", "failed"}, example="completed"),
     *             @OA\Property(
     *                 property="output",
     *                 type="object",
     *                 nullable=true,
     *                 example={"result": "The weather in Cairo is sunny, 28°C"}
     *             ),
     *             @OA\Property(property="error", type="string", nullable=true, example=null),
     *             @OA\Property(property="secret", type="string", example="your-callback-secret-token")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Callback received and processed successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Callback received and processed"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="run_id", type="string", format="uuid", example="660e8400-e29b-41d4-a716-446655440001"),
     *                 @OA\Property(property="status", type="string", example="completed")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="No active callback endpoint configured",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No active callback endpoint configured for this agent")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid secret token",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid secret")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Agent run not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Agent run not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Internal server error")
     *         )
     *     )
     * )
     */
    public function callback(Request $request): JsonResponse
    {
        try {
            // Validate request
            $validated = $request->validate([
                'run_id' => 'required|uuid',
                'status' => 'required|in:completed,failed',
                'output' => 'nullable|array',
                'error' => 'nullable|string',
                'secret' => 'required|string',
            ]);

            // Find the run
            $run = AgentRun::with('agent')->findOrFail($validated['run_id']);

            // Get the callback endpoint for this agent
            $callbackEndpoint = AgentEndpoint::where('agent_id', $run->agent_id)
                ->where('type', 'callback')
                ->where('is_active', true)
                ->first();

            if (!$callbackEndpoint) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active callback endpoint configured for this agent',
                ], 400);
            }

            // Validate secret
            if (!$callbackEndpoint->validateSecret($validated['secret'])) {
                Log::warning('Invalid callback secret', [
                    'run_id' => $validated['run_id'],
                    'agent_id' => $run->agent_id,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid secret',
                ], 401);
            }

            // Update run based on status
            if ($validated['status'] === 'completed') {
                $run->markAsCompleted($validated['output'] ?? []);
            } else {
                $run->markAsFailed($validated['error'] ?? 'Unknown error');
            }

            return response()->json([
                'success' => true,
                'message' => 'Callback received and processed',
                'data' => [
                    'run_id' => $run->id,
                    'status' => $run->status,
                ],
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Agent run not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Agent callback error: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
            ], 500);
        }
    }
}
