<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SubscriptionPlan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Subscriptions",
 *     description="Subscription management endpoints"
 * )
 */
class SubscriptionController extends Controller
{
    /**
     * Get all available subscription plans
     * 
     * @OA\Get(
     *     path="/api/v1/pricing/plans",
     *     summary="Get all subscription plans",
     *     description="Returns all active and published subscription plans grouped by name",
     *     tags={"Subscriptions"},
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
    public function plans()
    {
        $plans = SubscriptionPlan::with('billingCycle')
            ->where('is_active', true)
            ->where('is_published', true)
            ->orderBy('name')
            ->orderBy('billing_cycle_id')
            ->get()
            ->groupBy('name');

        return response()->json([
            'success' => true,
            'data' => $plans
        ]);
    }

    /**
     * Get current tenant subscription
     * 
     * @OA\Get(
     *     path="/api/v1/pricing/subscriptions/current",
     *     summary="Get current subscription",
     *     description="Returns the current active subscription for the authenticated tenant",
     *     tags={"Subscriptions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="subscription", type="object"),
     *                 @OA\Property(property="plan", type="object"),
     *                 @OA\Property(property="usage", type="object"),
     *                 @OA\Property(property="monthly_cost", type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="No tenant selected"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function current(Request $request)
    {
        $tenant = $request->user()->currentTenant;

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'No tenant selected'
            ], 400);
        }

        $subscription = $tenant->activeSubscription()
            ->with(['plan.billingCycle'])
            ->first();

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription',
                'data' => null
            ]);
        }

        // Get agent subscriptions
        $agentSubscriptions = $tenant->activeAgentSubscriptions()
            ->with('agent.tier')
            ->get();

        // Calculate usage
        $usagePercentage = $subscription->getUsagePercentage();
        $remainingExecutions = $subscription->getRemainingExecutions();

        return response()->json([
            'success' => true,
            'data' => [
                'subscription' => $subscription,
                'plan' => $subscription->plan,
                'billing_cycle' => $subscription->plan->billingCycle,
                'agent_subscriptions' => $agentSubscriptions,
                'usage' => [
                    'quota' => $subscription->execution_quota,
                    'used' => $subscription->executions_used,
                    'remaining' => $remainingExecutions,
                    'percentage' => $usagePercentage,
                ],
                'monthly_cost' => [
                    'base_plan' => $subscription->plan->getMonthlyEquivalentPrice(),
                    'agent_addons' => $tenant->getMonthlyAgentCost(),
                    'total' => $subscription->plan->getMonthlyEquivalentPrice() +
                        $tenant->getMonthlyAgentCost(),
                ],
            ]
        ]);
    }

    /**
     * Create new subscription
     * 
     * @OA\Post(
     *     path="/api/v1/pricing/subscriptions/create",
     *     summary="Create new subscription",
     *     description="Creates a new subscription for the tenant",
     *     tags={"Subscriptions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"plan_id"},
     *             @OA\Property(property="plan_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Subscription created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Tenant already has subscription"),
     *     @OA\Response(response=500, description="Failed to create subscription")
     * )
     */
    public function create(Request $request)
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
        ]);

        $tenant = $request->user()->currentTenant;

        // Check if already has subscription
        if ($tenant->activeSubscription()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant already has an active subscription'
            ], 400);
        }

        $plan = SubscriptionPlan::findOrFail($validated['plan_id']);
        $billingCycle = $plan->billingCycle;

        DB::beginTransaction();
        try {
            // Create subscription
            $subscription = Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'current_period_start' => now(),
                'current_period_end' => now()->addMonths($billingCycle->months),
                'next_billing_date' => now()->addMonths($billingCycle->months),
                'execution_quota' => $plan->included_executions,
                'executions_used' => 0,
                'auto_renew' => true,
            ]);

            // Create first invoice
            $invoice = Invoice::createForTenant(
                $tenant,
                now()->startOfMonth(),
                now()->endOfMonth(),
                $subscription
            );

            InvoiceLineItem::createBasePlan($invoice, $plan, $plan->final_price);
            $invoice->recalculateTotal();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Subscription created successfully',
                'data' => [
                    'subscription' => $subscription->load('plan.billingCycle'),
                    'invoice' => $invoice,
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upgrade subscription
     * 
     * @OA\Post(
     *     path="/api/v1/pricing/subscriptions/upgrade",
     *     summary="Upgrade subscription",
     *     description="Upgrades the current subscription to a higher plan",
     *     tags={"Subscriptions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"new_plan_id"},
     *             @OA\Property(property="new_plan_id", type="integer", example=2)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Subscription upgraded successfully"),
     *     @OA\Response(response=400, description="No active subscription to upgrade"),
     *     @OA\Response(response=500, description="Failed to upgrade subscription")
     * )
     */
    public function upgrade(Request $request)
    {
        $validated = $request->validate([
            'new_plan_id' => 'required|exists:subscription_plans,id',
        ]);

        $tenant = $request->user()->currentTenant;
        $currentSubscription = $tenant->activeSubscription;

        if (!$currentSubscription) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription to upgrade'
            ], 400);
        }

        $newPlan = SubscriptionPlan::findOrFail($validated['new_plan_id']);

        DB::beginTransaction();
        try {
            $currentSubscription->update([
                'plan_id' => $newPlan->id,
                'execution_quota' => $newPlan->included_executions,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Subscription upgraded successfully',
                'data' => $currentSubscription->load('plan.billingCycle')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to upgrade subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel subscription
     * 
     * @OA\Post(
     *     path="/api/v1/pricing/subscriptions/cancel",
     *     summary="Cancel subscription",
     *     description="Cancels the current subscription",
     *     tags={"Subscriptions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="reason", type="string", example="Too expensive"),
     *             @OA\Property(property="immediately", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Subscription cancelled successfully"),
     *     @OA\Response(response=400, description="No active subscription to cancel")
     * )
     */
    public function cancel(Request $request)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
            'immediately' => 'boolean',
        ]);

        $tenant = $request->user()->currentTenant;
        $subscription = $tenant->activeSubscription;

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription to cancel'
            ], 400);
        }

        $subscription->cancel(
            $validated['reason'] ?? null,
            $validated['immediately'] ?? false
        );

        return response()->json([
            'success' => true,
            'message' => $validated['immediately'] ?? false
                ? 'Subscription cancelled immediately'
                : 'Subscription will be cancelled at end of billing period',
            'data' => $subscription->load('plan')
        ]);
    }

    /**
     * Reactivate cancelled subscription
     */
    public function reactivate(Request $request)
    {
        $tenant = $request->user()->currentTenant;
        $subscription = $tenant->activeSubscription;

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No subscription found'
            ], 400);
        }

        if (!$subscription->cancelled_at) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription is not cancelled'
            ], 400);
        }

        $subscription->reactivate();

        return response()->json([
            'success' => true,
            'message' => 'Subscription reactivated successfully',
            'data' => $subscription->load('plan')
        ]);
    }

    /**
     * Get subscription history
     */
    public function history(Request $request)
    {
        $tenant = $request->user()->currentTenant;

        $subscriptions = Subscription::where('tenant_id', $tenant->id)
            ->with('plan.billingCycle')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'current' => $tenant->activeSubscription,
                'history' => $subscriptions,
            ]
        ]);
    }
}
