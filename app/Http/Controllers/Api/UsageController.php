<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AgentUsageTracking;
use App\Models\Agent;

/**
 * @OA\Tag(
 *     name="Usage",
 *     description="Usage tracking and analytics endpoints"
 * )
 */
class UsageController extends Controller
{
    /**
     * Current month usage summary
     * 
     * @OA\Get(
     *     path="/api/v1/pricing/usage/current",
     *     summary="Get current month usage",
     *     description="Returns usage summary for the current billing period",
     *     tags={"Usage"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="quota", type="integer"),
     *                 @OA\Property(property="used", type="integer"),
     *                 @OA\Property(property="remaining", type="integer"),
     *                 @OA\Property(property="percentage", type="number"),
     *                 @OA\Property(property="overage", type="integer"),
     *                 @OA\Property(property="overage_cost", type="number")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="No active subscription")
     * )
     */
    public function current(Request $request)
    {
        $tenant = $request->user()->currentTenant;
        $subscription = $tenant->activeSubscription;

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription'
            ], 400);
        }

        $summary = AgentUsageTracking::getTenantSummary($tenant->id);

        return response()->json([
            'success' => true,
            'data' => [
                'quota' => $subscription->execution_quota,
                'used' => $subscription->executions_used,
                'remaining' => $subscription->getRemainingExecutions(),
                'percentage' => $subscription->getUsagePercentage(),
                'overage' => $subscription->getOverageExecutions(),
                'overage_cost' => $subscription->calculateOverageCost(),
                'summary' => $summary,
            ]
        ]);
    }

    /**
     * Historical usage (last 6 months)
     * 
     * @OA\Get(
     *     path="/api/v1/pricing/usage/history",
     *     summary="Get usage history",
     *     description="Returns usage data for the last 6 months",
     *     tags={"Usage"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function history(Request $request)
    {
        $tenant = $request->user()->currentTenant;

        $history = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $summary = AgentUsageTracking::getTenantSummary(
                $tenant->id,
                $date->year,
                $date->month
            );

            $history[] = [
                'month' => $date->format('Y-m'),
                'usage' => $summary,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $history
        ]);
    }

    /**
     * Usage breakdown by agent
     * 
     * @OA\Get(
     *     path="/api/v1/pricing/usage/by-agent",
     *     summary="Get usage by agent",
     *     description="Returns usage breakdown grouped by agent for current month",
     *     tags={"Usage"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function byAgent(Request $request)
    {
        $tenant = $request->user()->currentTenant;

        $breakdown = AgentUsageTracking::getAgentBreakdown($tenant->id);

        return response()->json([
            'success' => true,
            'data' => $breakdown
        ]);
    }

    /**
     * Usage for specific agent
     * 
     * @OA\Get(
     *     path="/api/v1/pricing/usage/agent/{agent}",
     *     summary="Get specific agent usage",
     *     description="Returns usage statistics for a specific agent",
     *     tags={"Usage"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="agent",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function agentUsage(Request $request, Agent $agent)
    {
        $tenant = $request->user()->currentTenant;

        $usage = AgentUsageTracking::where('tenant_id', $tenant->id)
            ->where('agent_id', $agent->id)
            ->currentMonth()
            ->selectRaw('
                COUNT(*) as total_executions,
                SUM(ai_model_cost) as total_cost,
                SUM(charged_amount) as total_charged,
                AVG(execution_time_ms) as avg_execution_time
            ')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'agent' => $agent,
                'usage' => $usage,
            ]
        ]);
    }

    /**
     * Daily trend (last 30 days)
     * 
     * @OA\Get(
     *     path="/api/v1/pricing/usage/trend",
     *     summary="Get daily usage trend",
     *     description="Returns daily usage trend for the last 30 days",
     *     tags={"Usage"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function dailyTrend(Request $request)
    {
        $tenant = $request->user()->currentTenant;

        $trend = AgentUsageTracking::getDailyTrend($tenant->id, 30);

        return response()->json([
            'success' => true,
            'data' => $trend
        ]);
    }
}
