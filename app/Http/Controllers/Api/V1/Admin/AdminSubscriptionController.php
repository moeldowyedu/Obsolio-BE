<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Admin - Subscriptions",
 *     description="Admin endpoints for subscription instance management"
 * )
 */
class AdminSubscriptionController extends Controller
{
    /**
     * List all subscription instances with filtering and pagination.
     *
     * @OA\Get(
     *     path="/api/v1/admin/subscriptions",
     *     summary="List all subscriptions",
     *     description="Get paginated list of all subscription instances with filters",
     *     operationId="adminListSubscriptions",
     *     tags={"Admin - Subscriptions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer"), description="Page number"),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer"), description="Items per page (max 100)"),
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string", enum={"trialing", "active", "canceled", "expired", "past_due"}), description="Filter by status"),
     *     @OA\Parameter(name="plan_id", in="query", required=false, @OA\Schema(type="string", format="uuid"), description="Filter by plan ID"),
     *     @OA\Parameter(name="tenant_id", in="query", required=false, @OA\Schema(type="string", format="uuid"), description="Filter by tenant ID"),
     *     @OA\Response(
     *         response=200,
     *         description="Subscriptions retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->query('per_page', 20), 100);
        $status = $request->query('status');
        $planId = $request->query('plan_id');
        $tenantId = $request->query('tenant_id');

        $query = Subscription::query()
            ->with(['tenant:id,name,email,type', 'plan:id,name,tier,type']);

        // Status filter
        if ($status) {
            $query->where('status', $status);
        }

        // Plan filter
        if ($planId) {
            $query->where('plan_id', $planId);
        }

        // Tenant filter
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $subscriptions = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $subscriptions,
        ]);
    }

    /**
     * Get specific subscription details.
     *
     * @OA\Get(
     *     path="/api/v1/admin/subscriptions/{id}",
     *     summary="Get subscription details",
     *     description="Get detailed information about a specific subscription",
     *     operationId="adminGetSubscription",
     *     tags={"Admin - Subscriptions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid"), description="Subscription ID"),
     *     @OA\Response(
     *         response=200,
     *         description="Subscription details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Subscription not found")
     * )
     */
    public function show(string $id): JsonResponse
    {
        $subscription = Subscription::with([
            'tenant:id,name,email,type,status',
            'plan:id,name,tier,type,price_monthly,price_annual',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $subscription,
        ]);
    }

    /**
     * Create a new subscription.
     *
     * @OA\Post(
     *     path="/api/v1/admin/subscriptions",
     *     summary="Create new subscription",
     *     description="Create a new subscription for a tenant",
     *     operationId="adminCreateSubscription",
     *     tags={"Admin - Subscriptions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"tenant_id", "plan_id", "billing_cycle"},
     *             @OA\Property(property="tenant_id", type="string", format="uuid"),
     *             @OA\Property(property="plan_id", type="string", format="uuid"),
     *             @OA\Property(property="billing_cycle", type="string", enum={"monthly", "annual"}),
     *             @OA\Property(property="starts_at", type="string", format="date-time"),
     *             @OA\Property(property="trial_days", type="integer")
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
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'tenant_id' => 'required|uuid|exists:tenants,id',
            'plan_id' => 'required|uuid|exists:subscription_plans,id',
            'billing_cycle' => ['required', Rule::in(['monthly', 'annual'])],
            'starts_at' => 'nullable|date',
            'trial_days' => 'nullable|integer|min:0|max:365',
        ]);

        try {
            $plan = SubscriptionPlan::findOrFail($request->plan_id);
            $tenant = Tenant::findOrFail($request->tenant_id);
            $startsAt = $request->starts_at ? now()->parse($request->starts_at) : now();
            $trialDays = $request->trial_days ?? $plan->trial_days;

            $subscription = Subscription::create([
                'id' => Str::uuid(),
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'status' => $trialDays > 0 ? 'trialing' : 'active',
                'billing_cycle' => $request->billing_cycle,
                'starts_at' => $startsAt,
                'trial_ends_at' => $trialDays > 0 ? $startsAt->copy()->addDays($trialDays) : null,
                'current_period_start' => $startsAt,
                'current_period_end' => $request->billing_cycle === 'annual'
                    ? $startsAt->copy()->addYear()
                    : $startsAt->copy()->addMonth(),
                'metadata' => [
                    'created_by_admin' => true,
                    'admin_id' => auth()->id(),
                ],
            ]);

            // Log the creation
            activity()
                ->performedOn($subscription)
                ->causedBy(auth()->user())
                ->log('subscription_created_by_admin');

            return response()->json([
                'success' => true,
                'message' => 'Subscription created successfully',
                'data' => $subscription->load(['tenant', 'plan']),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create subscription',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update subscription information.
     *
     * @OA\Put(
     *     path="/api/v1/admin/subscriptions/{id}",
     *     summary="Update subscription",
     *     description="Update subscription information",
     *     operationId="adminUpdateSubscription",
     *     tags={"Admin - Subscriptions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid"), description="Subscription ID"),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", enum={"trialing", "active", "canceled", "expired", "past_due"}),
     *             @OA\Property(property="billing_cycle", type="string", enum={"monthly", "annual"}),
     *             @OA\Property(property="ends_at", type="string", format="date-time"),
     *             @OA\Property(property="trial_ends_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Subscription updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', Rule::in(['trialing', 'active', 'canceled', 'expired', 'past_due'])],
            'billing_cycle' => ['nullable', Rule::in(['monthly', 'annual'])],
            'ends_at' => 'nullable|date',
            'trial_ends_at' => 'nullable|date',
        ]);

        $subscription = Subscription::findOrFail($id);

        try {
            $oldData = $subscription->toArray();

            $updateData = [];
            if ($request->filled('status')) {
                $updateData['status'] = $request->status;
                if ($request->status === 'canceled' && !$subscription->canceled_at) {
                    $updateData['canceled_at'] = now();
                }
            }
            if ($request->filled('billing_cycle')) {
                $updateData['billing_cycle'] = $request->billing_cycle;
            }
            if ($request->has('ends_at')) {
                $updateData['ends_at'] = $request->ends_at ? now()->parse($request->ends_at) : null;
            }
            if ($request->has('trial_ends_at')) {
                $updateData['trial_ends_at'] = $request->trial_ends_at ? now()->parse($request->trial_ends_at) : null;
            }

            if (!empty($updateData)) {
                $subscription->update($updateData);
            }

            // Log the update
            activity()
                ->performedOn($subscription)
                ->causedBy(auth()->user())
                ->withProperties([
                    'old' => $oldData,
                    'new' => $subscription->fresh()->toArray(),
                ])
                ->log('subscription_updated_by_admin');

            return response()->json([
                'success' => true,
                'message' => 'Subscription updated successfully',
                'data' => $subscription->fresh()->load(['tenant', 'plan']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update subscription',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel a subscription.
     *
     * @OA\Post(
     *     path="/api/v1/admin/subscriptions/{id}/cancel",
     *     summary="Cancel subscription",
     *     description="Cancel a subscription",
     *     operationId="adminCancelSubscription",
     *     tags={"Admin - Subscriptions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid"), description="Subscription ID"),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="immediately", type="boolean", description="Cancel immediately or at period end"),
     *             @OA\Property(property="reason", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Subscription canceled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'immediately' => 'boolean',
            'reason' => 'nullable|string|max:500',
        ]);

        $subscription = Subscription::findOrFail($id);

        if ($subscription->status === 'canceled') {
            return response()->json([
                'success' => false,
                'message' => 'Subscription is already canceled',
            ], 400);
        }

        try {
            $subscription->update([
                'status' => 'canceled',
                'canceled_at' => now(),
                'ends_at' => $request->immediately ? now() : $subscription->current_period_end,
            ]);

            // Log the cancellation
            activity()
                ->performedOn($subscription)
                ->causedBy(auth()->user())
                ->withProperties([
                    'immediately' => $request->immediately ?? false,
                    'reason' => $request->reason,
                ])
                ->log('subscription_canceled_by_admin');

            return response()->json([
                'success' => true,
                'message' => 'Subscription canceled successfully',
                'data' => $subscription->fresh(),
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
     * Delete a subscription.
     *
     * @OA\Delete(
     *     path="/api/v1/admin/subscriptions/{id}",
     *     summary="Delete subscription",
     *     description="Delete a subscription (use with caution)",
     *     operationId="adminDeleteSubscription",
     *     tags={"Admin - Subscriptions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid"), description="Subscription ID"),
     *     @OA\Response(
     *         response=200,
     *         description="Subscription deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        $subscription = Subscription::findOrFail($id);

        try {
            // Log the deletion before deleting
            activity()
                ->performedOn($subscription)
                ->causedBy(auth()->user())
                ->withProperties($subscription->toArray())
                ->log('subscription_deleted_by_admin');

            $subscription->delete();

            return response()->json([
                'success' => true,
                'message' => 'Subscription deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete subscription',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get subscription statistics.
     *
     * @OA\Get(
     *     path="/api/v1/admin/subscriptions/statistics",
     *     summary="Get subscription statistics",
     *     description="Get statistics about all subscriptions",
     *     operationId="adminSubscriptionStatistics",
     *     tags={"Admin - Subscriptions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_subscriptions' => Subscription::count(),
            'by_status' => Subscription::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray(),
            'by_billing_cycle' => Subscription::select('billing_cycle', DB::raw('count(*) as count'))
                ->whereIn('status', ['trialing', 'active'])
                ->groupBy('billing_cycle')
                ->pluck('count', 'billing_cycle')
                ->toArray(),
            'active_subscriptions' => Subscription::whereIn('status', ['trialing', 'active'])->count(),
            'on_trial' => Subscription::where('status', 'trialing')
                ->where('trial_ends_at', '>', now())
                ->count(),
            'canceled_this_month' => Subscription::where('status', 'canceled')
                ->where('canceled_at', '>=', now()->startOfMonth())
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
