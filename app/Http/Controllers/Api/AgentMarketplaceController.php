<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Agent;
use App\Models\AgentSubscription;
use App\Models\AgentPricing;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Agent Marketplace",
 *     description="Agent browsing and subscription endpoints"
 * )
 */
class AgentMarketplaceController extends Controller
{
    /**
     * Public catalog (no auth required)
     * 
     * @OA\Get(
     *     path="/api/v1/pricing/agents/marketplace",
     *     summary="Get public agent catalog",
     *     description="Returns all active agents grouped by tier (no authentication required)",
     *     tags={"Agent Marketplace"},
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
    public function publicCatalog()
    {
        $agents = Agent::with(['tier', 'pricing'])
            ->where('is_active', true)
            ->orderBy('tier_id')
            ->orderBy('name')
            ->get()
            ->groupBy('tier.name');

        return response()->json([
            'success' => true,
            'data' => $agents
        ]);
    }

    /**
     * List all agents with tenant context
     * 
     * @OA\Get(
     *     path="/api/v1/pricing/agents/marketplace",
     *     summary="Browse agents with tenant context",
     *     description="Returns agents with subscription status and availability for the authenticated tenant",
     *     tags={"Agent Marketplace"},
     *     security={{"bearerAuth":{}}},
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
    public function index(Request $request)
    {
        $tenant = $request->user()->currentTenant;
        $subscription = $tenant->activeSubscription;

        if (!$subscription) {
            return $this->publicCatalog();
        }

        $plan = $subscription->plan;

        // Get all agents with pricing
        $agents = Agent::with(['tier', 'pricing'])
            ->where('is_active', true)
            ->get()
            ->map(function ($agent) use ($tenant, $plan) {
                $isSubscribed = $tenant->hasAgentSubscription($agent->id);
                $canSubscribe = !$isSubscribed &&
                    $plan->allowsAgentTier($agent->tier_id, 0);

                return [
                    'id' => $agent->id,
                    'name' => $agent->name,
                    'description' => $agent->description,
                    'tier' => $agent->tier,
                    'pricing' => $agent->pricing,
                    'is_subscribed' => $isSubscribed,
                    'can_subscribe' => $canSubscribe,
                ];
            })
            ->groupBy('tier.name');

        return response()->json([
            'success' => true,
            'data' => $agents
        ]);
    }

    /**
     * Get single agent details
     * 
     * @OA\Get(
     *     path="/api/v1/pricing/agents/marketplace/{agent}",
     *     summary="Get agent details",
     *     description="Returns detailed information about a specific agent",
     *     tags={"Agent Marketplace"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="agent",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=404, description="Agent not found")
     * )
     */
    public function show(Request $request, Agent $agent)
    {
        $tenant = $request->user()->currentTenant;

        $agent->load(['tier', 'pricing']);

        $isSubscribed = $tenant->hasAgentSubscription($agent->id);

        return response()->json([
            'success' => true,
            'data' => [
                'agent' => $agent,
                'is_subscribed' => $isSubscribed,
                'subscription' => $isSubscribed
                    ? AgentSubscription::where('tenant_id', $tenant->id)
                        ->where('agent_id', $agent->id)
                        ->first()
                    : null,
            ]
        ]);
    }

    /**
     * Subscribe to agent (add-on)
     * 
     * @OA\Post(
     *     path="/api/v1/pricing/agents/subscribe/{agent}",
     *     summary="Subscribe to agent",
     *     description="Creates a new agent subscription (add-on) for the tenant",
     *     tags={"Agent Marketplace"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="agent",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Successfully subscribed to agent",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Already subscribed or plan limit reached"),
     *     @OA\Response(response=404, description="Agent pricing not found")
     * )
     */
    public function subscribe(Request $request, Agent $agent)
    {
        $tenant = $request->user()->currentTenant;
        $subscription = $tenant->activeSubscription;

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription found'
            ], 400);
        }

        // Check if already subscribed
        if ($tenant->hasAgentSubscription($agent->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Already subscribed to this agent'
            ], 400);
        }

        // Check plan limits
        $plan = $subscription->plan;
        $currentCount = $tenant->activeAgentSubscriptions()
            ->whereHas('agent', function ($q) use ($agent) {
                $q->where('tier_id', $agent->tier_id);
            })
            ->count();

        if (!$plan->allowsAgentTier($agent->tier_id, $currentCount)) {
            return response()->json([
                'success' => false,
                'message' => 'Plan limit reached for this agent tier',
                'current_count' => $currentCount,
            ], 400);
        }

        // Get pricing
        $pricing = $agent->pricing()->where('is_active', true)->first();

        if (!$pricing) {
            return response()->json([
                'success' => false,
                'message' => 'Agent pricing not found'
            ], 404);
        }

        DB::beginTransaction();
        try {
            // Create agent subscription
            $agentSubscription = AgentSubscription::create([
                'tenant_id' => $tenant->id,
                'agent_id' => $agent->id,
                'monthly_price' => $pricing->monthly_price,
                'status' => 'active',
                'started_at' => now(),
                'current_period_start' => now()->startOfMonth(),
                'current_period_end' => now()->endOfMonth(),
                'next_billing_date' => now()->addMonth()->startOfMonth(),
                'auto_renew' => true,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Successfully subscribed to agent',
                'data' => $agentSubscription->load('agent.tier')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to subscribe to agent',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Unsubscribe from agent
     * 
     * @OA\Post(
     *     path="/api/v1/pricing/agents/unsubscribe/{agent}",
     *     summary="Unsubscribe from agent",
     *     description="Cancels the agent subscription for the tenant",
     *     tags={"Agent Marketplace"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="agent",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="Successfully unsubscribed from agent"),
     *     @OA\Response(response=404, description="Not subscribed to this agent")
     * )
     */
    public function unsubscribe(Request $request, Agent $agent)
    {
        $tenant = $request->user()->currentTenant;

        $agentSubscription = AgentSubscription::where('tenant_id', $tenant->id)
            ->where('agent_id', $agent->id)
            ->active()
            ->first();

        if (!$agentSubscription) {
            return response()->json([
                'success' => false,
                'message' => 'Not subscribed to this agent'
            ], 404);
        }

        $agentSubscription->cancel();

        return response()->json([
            'success' => true,
            'message' => 'Successfully unsubscribed from agent',
            'data' => $agentSubscription
        ]);
    }

    /**
     * Get tenant's active agents
     * 
     * @OA\Get(
     *     path="/api/v1/pricing/agents/my-agents",
     *     summary="Get subscribed agents",
     *     description="Returns all active agent subscriptions for the tenant",
     *     tags={"Agent Marketplace"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function myAgents(Request $request)
    {
        $tenant = $request->user()->currentTenant;

        $agents = $tenant->activeAgentSubscriptions()
            ->with('agent.tier')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $agents
        ]);
    }

    /**
     * Get available agent slots
     * 
     * @OA\Get(
     *     path="/api/v1/pricing/agents/available-slots",
     *     summary="Get available agent slots",
     *     description="Returns the number of available agent slots by tier for the tenant",
     *     tags={"Agent Marketplace"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_slots", type="integer"),
     *                 @OA\Property(property="used_slots", type="integer"),
     *                 @OA\Property(property="remaining_by_tier", type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="No active subscription")
     * )
     */
    public function availableSlots(Request $request)
    {
        $tenant = $request->user()->currentTenant;
        $subscription = $tenant->activeSubscription;

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription'
            ], 400);
        }

        $plan = $subscription->plan;

        // Count current agents by tier
        $currentAgents = $tenant->activeAgentSubscriptions()
            ->with('agent.tier')
            ->get()
            ->groupBy('agent.tier.name')
            ->map->count()
            ->toArray();

        $remainingSlots = $plan->getRemainingSlots($currentAgents);

        return response()->json([
            'success' => true,
            'data' => [
                'total_slots' => $plan->max_agent_slots,
                'used_slots' => $tenant->activeAgentSubscriptions()->count(),
                'remaining_by_tier' => $remainingSlots,
            ]
        ]);
    }

    /**
     * Check if can add specific agent
     * 
     * @OA\Post(
     *     path="/api/v1/pricing/agents/can-add/{agent}",
     *     summary="Check if can add agent",
     *     description="Validates if the tenant can subscribe to a specific agent",
     *     tags={"Agent Marketplace"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="agent",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Validation result",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="can_add", type="boolean"),
     *             @OA\Property(property="reason", type="string", nullable=true)
     *         )
     *     )
     * )
     */
    public function canAddAgent(Request $request, Agent $agent)
    {
        $tenant = $request->user()->currentTenant;
        $subscription = $tenant->activeSubscription;

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription',
                'can_add' => false,
            ]);
        }

        $plan = $subscription->plan;

        // Check if already subscribed
        if ($tenant->hasAgentSubscription($agent->id)) {
            return response()->json([
                'success' => true,
                'can_add' => false,
                'reason' => 'already_subscribed',
            ]);
        }

        // Check tier limit
        $currentCount = $tenant->activeAgentSubscriptions()
            ->whereHas('agent', function ($q) use ($agent) {
                $q->where('tier_id', $agent->tier_id);
            })
            ->count();

        $canAdd = $plan->allowsAgentTier($agent->tier_id, $currentCount);

        return response()->json([
            'success' => true,
            'can_add' => $canAdd,
            'reason' => $canAdd ? null : 'tier_limit_reached',
            'current_count' => $currentCount,
        ]);
    }
}
