<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionPlanController extends Controller
{
    /**
     * Get all active subscription plans (DEPRECATED)
     *
     * @OA\Get(
     *     path="/api/v1/subscription-plans",
     *     summary="Get all subscription plans (DEPRECATED)",
     *     description="DEPRECATED: Use /api/v1/pricing/plans instead. This endpoint will be removed in v2.0. Returns all active subscription plans.",
     *     deprecated=true,
     *     tags={"Pricing"},
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filter by plan type (personal or organization)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"personal", "organization"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(type="object")
     *             )
     *         ),
     *         @OA\Header(
     *             header="X-API-Deprecated",
     *             description="Deprecation notice",
     *             @OA\Schema(type="string", example="true")
     *         ),
     *         @OA\Header(
     *             header="X-API-Deprecation-Info",
     *             description="Migration information",
     *             @OA\Schema(type="string", example="Use /api/v1/pricing/plans instead. This endpoint will be removed in v2.0")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $type = $request->query('type'); // personal or organization

        $plans = SubscriptionPlan::where('is_active', true)
            ->when($type, function ($query, $type) {
                return $query->where('type', $type);
            })
            ->orderBy('price_monthly', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $plans,
        ]);
    }

    /**
     * Get a specific plan by ID
     *
     * @OA\Get(
     *     path="/api/v1/pricing/plans/{id}",
     *     summary="Get subscription plan by ID",
     *     description="Returns detailed information about a specific subscription plan",
     *     tags={"Pricing"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Plan ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Plan not found")
     * )
     *
     * @OA\Get(
     *     path="/api/v1/subscription-plans/{id}",
     *     summary="Get subscription plan by ID (DEPRECATED)",
     *     description="DEPRECATED: Use /api/v1/pricing/plans/{id} instead. This endpoint will be removed in v2.0.",
     *     deprecated=true,
     *     tags={"Pricing"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Plan ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         ),
     *         @OA\Header(
     *             header="X-API-Deprecated",
     *             description="Deprecation notice",
     *             @OA\Schema(type="string", example="true")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Plan not found")
     * )
     */
    public function show(string $id): JsonResponse
    {
        $plan = SubscriptionPlan::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $plan,
        ]);
    }

    /**
     * Get plan recommendations based on usage.
     */
    public function recommendations(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        $currentPlan = $tenant->plan;

        // Logic to recommend plans based on usage
        $recommendations = SubscriptionPlan::where('is_active', true)
            ->where('type', $tenant->type)
            ->where('tier', '!=', $currentPlan?->tier)
            ->orderBy('price_monthly', 'asc')
            ->limit(3)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'current_plan' => $currentPlan,
                'recommendations' => $recommendations,
            ],
        ]);
    }
}