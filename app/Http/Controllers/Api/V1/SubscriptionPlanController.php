<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionPlanController extends Controller
{
    /**
     * Get all active subscription plans.
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
     * Get a specific plan by ID.
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