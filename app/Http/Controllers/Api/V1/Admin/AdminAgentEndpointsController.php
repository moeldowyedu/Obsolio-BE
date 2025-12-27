<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentEndpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Admin - Agent Endpoints",
 *     description="Admin endpoints for managing agent webhook endpoints"
 * )
 */
class AdminAgentEndpointsController extends Controller
{
    /**
     * List all agent endpoints.
     *
     * @OA\Get(
     *     path="/api/v1/admin/agent-endpoints",
     *     summary="List all agent endpoints",
     *     description="Get all agent endpoints with filters",
     *     operationId="adminListAgentEndpoints",
     *     tags={"Admin - Agent Endpoints"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="agent_id", in="query", required=false, @OA\Schema(type="string", format="uuid"), description="Filter by agent ID"),
     *     @OA\Parameter(name="type", in="query", required=false, @OA\Schema(type="string", enum={"trigger", "callback"}), description="Filter by endpoint type"),
     *     @OA\Parameter(name="is_active", in="query", required=false, @OA\Schema(type="boolean"), description="Filter by active status"),
     *     @OA\Response(
     *         response=200,
     *         description="Agent endpoints retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="string", format="uuid"),
     *                 @OA\Property(property="agent_id", type="string", format="uuid"),
     *                 @OA\Property(property="agent_name", type="string"),
     *                 @OA\Property(property="type", type="string", enum={"trigger", "callback"}),
     *                 @OA\Property(property="url", type="string"),
     *                 @OA\Property(property="secret", type="string"),
     *                 @OA\Property(property="is_active", type="boolean"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             ))
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $agentId = $request->query('agent_id');
        $type = $request->query('type');
        $isActive = $request->query('is_active');

        $query = AgentEndpoint::query()
            ->with('agent:id,name,slug')
            ->when($agentId, fn($q, $id) => $q->where('agent_id', $id))
            ->when($type, fn($q, $t) => $q->where('type', $t))
            ->when($isActive !== null, fn($q) => $q->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN)))
            ->orderBy('created_at', 'desc');

        $endpoints = $query->get()->map(function ($endpoint) {
            return [
                'id' => $endpoint->id,
                'agent_id' => $endpoint->agent_id,
                'agent_name' => $endpoint->agent?->name ?? 'Unknown Agent',
                'type' => $endpoint->type,
                'url' => $endpoint->url,
                'secret' => $endpoint->secret,
                'is_active' => $endpoint->is_active,
                'created_at' => $endpoint->created_at?->toISOString(),
                'updated_at' => $endpoint->updated_at?->toISOString(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $endpoints,
        ]);
    }

    /**
     * Create a new agent endpoint.
     *
     * @OA\Post(
     *     path="/api/v1/admin/agent-endpoints",
     *     summary="Create agent endpoint",
     *     description="Create a new agent endpoint (trigger or callback)",
     *     operationId="adminCreateAgentEndpoint",
     *     tags={"Admin - Agent Endpoints"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"agent_id", "type", "url"},
     *             @OA\Property(property="agent_id", type="string", format="uuid"),
     *             @OA\Property(property="type", type="string", enum={"trigger", "callback"}),
     *             @OA\Property(property="url", type="string", format="uri"),
     *             @OA\Property(property="secret", type="string", description="Optional, auto-generated if not provided"),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Agent endpoint created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'agent_id' => 'required|uuid|exists:agents,id',
            'type' => 'required|in:trigger,callback',
            'url' => 'required|url|max:500',
            'method' => 'sometimes|string|in:GET,POST,PUT,PATCH,DELETE|max:10',
            'headers' => 'sometimes|array',
            'secret' => 'sometimes|string|min:16|max:255',
            'timeout_ms' => 'sometimes|integer|min:1000|max:300000',
            'retries' => 'sometimes|integer|min:0|max:10',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            // Check if endpoint already exists for this agent and type
            $existing = AgentEndpoint::where('agent_id', $request->agent_id)
                ->where('type', $request->type)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => "An {$request->type} endpoint already exists for this agent. Please update the existing endpoint instead.",
                ], 422);
            }

            $endpoint = AgentEndpoint::create([
                'agent_id' => $request->agent_id,
                'type' => $request->type,
                'url' => $request->url,
                'method' => $request->method ?? 'POST',
                'headers' => $request->headers ?? [],
                'secret' => $request->secret ?? Str::random(32),
                'timeout_ms' => $request->timeout_ms ?? 10000,
                'retries' => $request->retries ?? 3,
                'is_active' => $request->is_active ?? true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Agent endpoint created successfully',
                'data' => $endpoint,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create agent endpoint',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get specific agent endpoint details.
     *
     * @OA\Get(
     *     path="/api/v1/admin/agent-endpoints/{id}",
     *     summary="Get agent endpoint details",
     *     description="Get detailed information about a specific agent endpoint",
     *     operationId="adminGetAgentEndpoint",
     *     tags={"Admin - Agent Endpoints"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Response(
     *         response=200,
     *         description="Agent endpoint retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Agent endpoint not found")
     * )
     */
    public function show(string $id): JsonResponse
    {
        $endpoint = AgentEndpoint::with('agent')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $endpoint->id,
                'agent_id' => $endpoint->agent_id,
                'agent' => $endpoint->agent ? [
                    'id' => $endpoint->agent->id,
                    'name' => $endpoint->agent->name,
                    'slug' => $endpoint->agent->slug,
                ] : null,
                'type' => $endpoint->type,
                'url' => $endpoint->url,
                'secret' => $endpoint->secret,
                'is_active' => $endpoint->is_active,
                'created_at' => $endpoint->created_at?->toISOString(),
                'updated_at' => $endpoint->updated_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Update an agent endpoint.
     *
     * @OA\Put(
     *     path="/api/v1/admin/agent-endpoints/{id}",
     *     summary="Update agent endpoint",
     *     description="Update an existing agent endpoint",
     *     operationId="adminUpdateAgentEndpoint",
     *     tags={"Admin - Agent Endpoints"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="url", type="string", format="uri"),
     *             @OA\Property(property="secret", type="string"),
     *             @OA\Property(property="is_active", type="boolean")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Agent endpoint updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Agent endpoint not found")
     * )
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $endpoint = AgentEndpoint::findOrFail($id);

        $request->validate([
            'url' => 'sometimes|url|max:500',
            'method' => 'sometimes|string|in:GET,POST,PUT,PATCH,DELETE|max:10',
            'headers' => 'sometimes|array',
            'secret' => 'sometimes|string|min:16|max:255',
            'timeout_ms' => 'sometimes|integer|min:1000|max:300000',
            'retries' => 'sometimes|integer|min:0|max:10',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            $endpoint->update($request->only(['url', 'method', 'headers', 'secret', 'timeout_ms', 'retries', 'is_active']));

            return response()->json([
                'success' => true,
                'message' => 'Agent endpoint updated successfully',
                'data' => $endpoint->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update agent endpoint',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete an agent endpoint.
     *
     * @OA\Delete(
     *     path="/api/v1/admin/agent-endpoints/{id}",
     *     summary="Delete agent endpoint",
     *     description="Delete an agent endpoint",
     *     operationId="adminDeleteAgentEndpoint",
     *     tags={"Admin - Agent Endpoints"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Response(
     *         response=200,
     *         description="Agent endpoint deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Agent endpoint not found")
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        $endpoint = AgentEndpoint::findOrFail($id);

        try {
            $endpoint->delete();

            return response()->json([
                'success' => true,
                'message' => 'Agent endpoint deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete agent endpoint',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
