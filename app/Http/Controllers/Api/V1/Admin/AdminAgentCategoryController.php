<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Admin - Agent Categories",
 *     description="Admin endpoints for agent category management"
 * )
 */
class AdminAgentCategoryController extends Controller
{
    /**
     * List all agent categories.
     *
     * @OA\Get(
     *     path="/api/v1/admin/agent-categories",
     *     summary="List all agent categories",
     *     description="Get all agent categories ordered by display_order with agents count",
     *     operationId="adminListAgentCategories",
     *     tags={"Admin - Agent Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Agent categories list retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="string", format="uuid"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="slug", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="icon", type="string"),
     *                 @OA\Property(property="display_order", type="integer"),
     *                 @OA\Property(property="agents_count", type="integer"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             ))
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $categories = AgentCategory::withCount('agents')
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Create a new agent category.
     *
     * @OA\Post(
     *     path="/api/v1/admin/agent-categories",
     *     summary="Create agent category",
     *     description="Create a new agent category",
     *     operationId="adminCreateAgentCategory",
     *     tags={"Admin - Agent Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "slug"},
     *             @OA\Property(property="name", type="string", example="Data Processing"),
     *             @OA\Property(property="slug", type="string", example="data-processing"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="icon", type="string", example="database"),
     *             @OA\Property(property="display_order", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Agent category created successfully",
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
            'name' => 'required|string|max:255',
            'slug' => 'required|string|unique:agent_categories,slug|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:255',
            'display_order' => 'nullable|integer|min:0',
        ]);

        try {
            $category = AgentCategory::create([
                'name' => $request->name,
                'slug' => $request->slug,
                'description' => $request->description,
                'icon' => $request->icon,
                'display_order' => $request->display_order ?? 0,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Agent category created successfully',
                'data' => $category,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create agent category',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an agent category.
     *
     * @OA\Put(
     *     path="/api/v1/admin/agent-categories/{id}",
     *     summary="Update agent category",
     *     description="Update an existing agent category",
     *     operationId="adminUpdateAgentCategory",
     *     tags={"Admin - Agent Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="slug", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="icon", type="string"),
     *             @OA\Property(property="display_order", type="integer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Agent category updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Agent category not found")
     * )
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $category = AgentCategory::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|unique:agent_categories,slug,' . $id . '|max:255',
            'description' => 'sometimes|nullable|string',
            'icon' => 'sometimes|nullable|string|max:255',
            'display_order' => 'sometimes|integer|min:0',
        ]);

        try {
            $category->update($request->only([
                'name',
                'slug',
                'description',
                'icon',
                'display_order',
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Agent category updated successfully',
                'data' => $category->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update agent category',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete an agent category.
     *
     * @OA\Delete(
     *     path="/api/v1/admin/agent-categories/{id}",
     *     summary="Delete agent category",
     *     description="Delete an agent category (only if no agents exist in category)",
     *     operationId="adminDeleteAgentCategory",
     *     tags={"Admin - Agent Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Response(
     *         response=200,
     *         description="Agent category deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Cannot delete category with agents"),
     *     @OA\Response(response=404, description="Agent category not found")
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        $category = AgentCategory::findOrFail($id);

        // Check if category has agents
        $agentsCount = $category->agents()->count();

        if ($agentsCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete category with {$agentsCount} agents. Please reassign or delete the agents first.",
            ], 400);
        }

        try {
            $category->delete();

            return response()->json([
                'success' => true,
                'message' => 'Agent category deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete agent category',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
