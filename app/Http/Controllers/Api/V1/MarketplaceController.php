<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketplaceController extends Controller
{
    /**
     * Get all marketplace agents.
     */
    public function index(Request $request): JsonResponse
    {
        $category = $request->query('category');
        $search = $request->query('search');
        $featured = $request->query('featured');

        $agents = Agent::marketplace()
            ->active()
            ->when($category, function ($query, $category) {
                return $query->byCategory($category);
            })
            ->when($featured, function ($query) {
                return $query->featured();
            })
            ->when($search, function ($query, $search) {
                return $query->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('description', 'ILIKE', "%{$search}%");
            })
            ->orderBy('is_featured', 'desc')
            ->orderBy('rating', 'desc')
            ->paginate(12);

        return response()->json([
            'success' => true,
            'data' => $agents,
        ]);
    }

    /**
     * Get featured agents.
     */
    public function featured(): JsonResponse
    {
        $agents = Agent::marketplace()
            ->active()
            ->featured()
            ->orderBy('rating', 'desc')
            ->limit(6)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $agents,
        ]);
    }

    /**
     * Get specific agent details from marketplace.
     *
     * @OA\Get(
     *     path="/v1/marketplace/agents/{id}",
     *     summary="Get marketplace agent details",
     *     description="Get detailed information about a specific agent in the marketplace",
     *     operationId="getMarketplaceAgent",
     *     tags={"Marketplace"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid"), description="Agent ID"),
     *     @OA\Response(
     *         response=200,
     *         description="Agent details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Agent not found")
     * )
     */
    public function show(string $id): JsonResponse
    {
        $agent = Agent::marketplace()
            ->active()
            ->with('category')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $agent,
        ]);
    }

    /**
     * Get agents by category.
     */
    public function byCategory(string $category): JsonResponse
    {
        $agents = Agent::marketplace()
            ->active()
            ->byCategory($category)
            ->orderBy('rating', 'desc')
            ->paginate(12);

        return response()->json([
            'success' => true,
            'data' => $agents,
        ]);
    }

    /**
     * Get marketplace categories.
     */
    public function categories(): JsonResponse
    {
        $categories = Agent::marketplace()
            ->active()
            ->select('category')
            ->groupBy('category')
            ->get()
            ->pluck('category')
            ->map(function ($category) {
                return [
                    'value' => $category,
                    'label' => ucfirst($category),
                    'count' => Agent::marketplace()->active()->byCategory($category)->count(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Get marketplace statistics.
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total_agents' => Agent::marketplace()->active()->count(),
            'total_categories' => Agent::marketplace()->active()->distinct('category')->count('category'),
            'featured_agents' => Agent::marketplace()->active()->featured()->count(),
            'total_installs' => Agent::marketplace()->sum('total_installs'),
            'average_rating' => round(Agent::marketplace()->active()->avg('rating'), 1),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}