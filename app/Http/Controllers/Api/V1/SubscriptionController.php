<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
    /**
     * Get current tenant subscription.
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