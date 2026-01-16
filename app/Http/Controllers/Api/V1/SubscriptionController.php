<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Tenant - Subscriptions",
 *     description="Tenant dashboard: Subscription management"
 * )
 */
class SubscriptionController extends Controller
{
    /**
     * Get current tenant subscription.
     *
     * @OA\Get(
     *     path="/api/v1/tenant/subscription/current",
     *     summary="Get current subscription",
     *     description="Returns the current active subscription for the authenticated tenant, including plan details, trial status, and usage information.",
     *     operationId="getTenantCurrentSubscription",
     *     tags={"Tenant - Subscriptions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="subscription",
     *                     type="object",
     *                     @OA\Property(property="id", type="string", format="uuid"),
     *                     @OA\Property(property="tenant_id", type="string"),
     *                     @OA\Property(property="plan_id", type="string", format="uuid"),
     *                     @OA\Property(property="status", type="string", enum={"trialing", "active", "canceled", "past_due"}),
     *                     @OA\Property(property="billing_cycle", type="string", enum={"monthly", "annual"}),
     *                     @OA\Property(property="starts_at", type="string", format="date-time"),
     *                     @OA\Property(property="trial_ends_at", type="string", format="date-time", nullable=true),
     *                     @OA\Property(property="current_period_start", type="string", format="date-time"),
     *                     @OA\Property(property="current_period_end", type="string", format="date-time"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="plan", type="object",
     *                         @OA\Property(property="id", type="string", format="uuid"),
     *                         @OA\Property(property="name", type="string", example="Professional"),
     *                         @OA\Property(property="description", type="string"),
     *                         @OA\Property(property="base_price", type="number", format="float"),
     *                         @OA\Property(property="final_price", type="number", format="float"),
     *                         @OA\Property(property="features", type="array", @OA\Items(type="string"))
     *                     )
     *                 ),
     *                 @OA\Property(property="trial_days_remaining", type="integer", example=7),
     *                 @OA\Property(property="is_on_trial", type="boolean", example=true),
     *                 @OA\Property(property="is_active", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No active subscription found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No active subscription found")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function current(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $subscription = $tenant->activeSubscription()
            ->with('plan')
            ->first();

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'subscription' => $subscription,
                'trial_days_remaining' => $subscription->trialDaysRemaining(),
                'is_on_trial' => $subscription->isOnTrial(),
                'is_active' => $subscription->isActive(),
            ],
        ]);
    }

    /**
     * Create a new subscription.
     *
     * @OA\Post(
     *     path="/api/v1/tenant/subscription/subscribe",
     *     summary="Create new subscription",
     *     description="Creates a new subscription for the tenant. If an active subscription exists, it will be canceled first.",
     *     operationId="createTenantSubscription",
     *     tags={"Tenant - Subscriptions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"plan_id", "billing_cycle"},
     *             @OA\Property(property="plan_id", type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000"),
     *             @OA\Property(property="billing_cycle", type="string", enum={"monthly", "annual"}, example="monthly")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Subscription created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Subscription created successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Failed to create subscription")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|uuid|exists:subscription_plans,id',
            'billing_cycle' => 'required|in:monthly,annual',
        ]);

        $tenant = $request->user()->tenant;
        $plan = SubscriptionPlan::findOrFail($request->plan_id);

        try {
            return DB::transaction(function () use ($request, $tenant, $plan) {
                // Cancel existing active subscription if any
                $existingSubscription = $tenant->activeSubscription()->first();
                if ($existingSubscription) {
                    $existingSubscription->update([
                        'status' => 'canceled',
                        'canceled_at' => now(),
                    ]);
                }

                // Create new subscription
                $subscription = Subscription::create([
                    'tenant_id' => $tenant->id,
                    'plan_id' => $plan->id,
                    'billing_cycle' => $request->billing_cycle,
                    'status' => 'trialing',
                    'starts_at' => now(),
                    'trial_ends_at' => now()->addDays($plan->trial_days),
                    'current_period_start' => now(),
                    'current_period_end' => $request->billing_cycle === 'monthly'
                        ? now()->addMonth()
                        : now()->addYear(),
                ]);

                // Update tenant plan
                $tenant->update(['plan_id' => $plan->id]);

                return response()->json([
                    'success' => true,
                    'message' => 'Subscription created successfully',
                    'data' => $subscription->load('plan'),
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create subscription',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Change subscription plan.
     *
     * @OA\Put(
     *     path="/api/v1/tenant/subscription/change-plan",
     *     summary="Change subscription plan",
     *     description="Changes the current subscription to a different plan.",
     *     operationId="changeTenantSubscriptionPlan",
     *     tags={"Tenant - Subscriptions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"plan_id", "billing_cycle"},
     *             @OA\Property(property="plan_id", type="string", format="uuid"),
     *             @OA\Property(property="billing_cycle", type="string", enum={"monthly", "annual"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Plan changed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Plan changed successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="No active subscription found"),
     *     @OA\Response(response=500, description="Failed to change plan")
     * )
     */
    public function changePlan(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|uuid|exists:subscription_plans,id',
            'billing_cycle' => 'required|in:monthly,annual',
        ]);

        $tenant = $request->user()->tenant;
        $newPlan = SubscriptionPlan::findOrFail($request->plan_id);

        $subscription = $tenant->activeSubscription()->firstOrFail();

        try {
            $subscription->update([
                'plan_id' => $newPlan->id,
                'billing_cycle' => $request->billing_cycle,
            ]);

            $tenant->update(['plan_id' => $newPlan->id]);

            return response()->json([
                'success' => true,
                'message' => 'Plan changed successfully',
                'data' => $subscription->fresh()->load('plan'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to change plan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel subscription.
     *
     * @OA\Post(
     *     path="/api/v1/tenant/subscription/cancel",
     *     summary="Cancel subscription",
     *     description="Cancels the current active subscription.",
     *     operationId="cancelTenantSubscription",
     *     tags={"Tenant - Subscriptions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Subscription canceled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Subscription canceled successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="No active subscription found"),
     *     @OA\Response(response=500, description="Failed to cancel subscription")
     * )
     */
    public function cancel(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        $subscription = $tenant->activeSubscription()->firstOrFail();

        try {
            $subscription->update([
                'status' => 'canceled',
                'canceled_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Subscription canceled successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel subscription',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resume canceled subscription.
     *
     * @OA\Post(
     *     path="/api/v1/tenant/subscription/resume",
     *     summary="Resume cancelled subscription",
     *     description="Resumes a previously cancelled subscription.",
     *     operationId="resumeTenantSubscription",
     *     tags={"Tenant - Subscriptions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Subscription resumed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Subscription resumed successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="No cancelled subscription found"),
     *     @OA\Response(response=500, description="Failed to resume subscription")
     * )
     */
    public function resume(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $subscription = Subscription::where('tenant_id', $tenant->id)
            ->where('status', 'canceled')
            ->latest()
            ->firstOrFail();

        try {
            $subscription->update([
                'status' => 'active',
                'canceled_at' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Subscription resumed successfully',
                'data' => $subscription->fresh()->load('plan'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to resume subscription',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get subscription history.
     *
     * @OA\Get(
     *     path="/api/v1/tenant/subscription/history",
     *     summary="Get subscription history",
     *     description="Returns paginated subscription history for the tenant, including all past and current subscriptions.",
     *     operationId="getTenantSubscriptionHistory",
     *     tags={"Tenant - Subscriptions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="data", type="array", @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="string", format="uuid"),
     *                     @OA\Property(property="plan_id", type="string", format="uuid"),
     *                     @OA\Property(property="status", type="string"),
     *                     @OA\Property(property="billing_cycle", type="string"),
     *                     @OA\Property(property="starts_at", type="string", format="date-time"),
     *                     @OA\Property(property="canceled_at", type="string", format="date-time", nullable=true),
     *                     @OA\Property(property="plan", type="object")
     *                 )),
     *                 @OA\Property(property="last_page", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="total", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function history(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $subscriptions = Subscription::where('tenant_id', $tenant->id)
            ->with('plan')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $subscriptions,
        ]);
    }
}