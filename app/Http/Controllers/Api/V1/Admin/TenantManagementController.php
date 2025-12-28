<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TenantManagementController extends Controller
{
    /**
     * List all tenants with advanced filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $type = $request->query('type');
        $status = $request->query('status');
        $planId = $request->query('plan_id');
        $hasSubscription = $request->query('has_subscription');
        $sortBy = $request->query('sort_by', 'created_at');
        $sortOrder = $request->query('sort_order', 'desc');

        $query = Tenant::query()
            ->with([
                'activeSubscription.plan',
                'organization',
                'memberships'
            ])
            ->withCount('memberships');

        // Search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('email', 'ILIKE', "%{$search}%")
                    ->orWhere('subdomain_preference', 'ILIKE', "%{$search}%");
            });
        }

        // Type filter
        if ($type) {
            $query->where('type', $type);
        }

        // Status filter
        if ($status) {
            $query->where('status', $status);
        }

        // Plan filter (via active subscription)
        if ($planId) {
            $query->whereHas('activeSubscription', function ($q) use ($planId) {
                $q->where('plan_id', $planId);
            });
        }

        // Subscription status filter
        if ($hasSubscription !== null) {
            if ($hasSubscription === 'true') {
                $query->has('activeSubscription');
            } else {
                $query->doesntHave('activeSubscription');
            }
        }

        // Sorting
        $allowedSortFields = ['created_at', 'name', 'email', 'type', 'status'];
        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $tenants = $query->paginate($request->query('per_page', 20));

        // Add computed fields to each tenant
        $tenants->getCollection()->transform(function ($tenant) {
            $tenant->is_on_trial = $tenant->isOnTrial();
            $tenant->trial_days_remaining = $tenant->trialDaysRemaining();
            $tenant->billing_cycle = $tenant->billingCycle();
            $tenant->has_active_subscription = $tenant->hasActiveSubscription();
            return $tenant;
        });

        return response()->json([
            'success' => true,
            'data' => $tenants,
        ]);
    }

    /**
     * Get detailed information about a specific tenant.
     */
    public function show(string $id): JsonResponse
    {
        $tenant = Tenant::with([
            'activeSubscription.plan',
            'subscriptions.plan',
            'organization',
            'organizations',
            'memberships.user',
            'invoices' => function ($query) {
                $query->latest()->limit(10);
            },
            'paymentMethods'
        ])
            ->withCount(['memberships', 'invoices', 'subscriptions'])
            ->findOrFail($id);

        // Add computed fields
        $tenant->is_on_trial = $tenant->isOnTrial();
        $tenant->trial_days_remaining = $tenant->trialDaysRemaining();
        $tenant->billing_cycle = $tenant->billingCycle();
        $tenant->has_active_subscription = $tenant->hasActiveSubscription();

        return response()->json([
            'success' => true,
            'data' => $tenant,
        ]);
    }

    /**
     * Update tenant status.
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'status' => ['required', Rule::in(['pending_verification', 'active', 'inactive', 'suspended'])],
            'reason' => 'nullable|string|max:500',
        ]);

        $tenant = Tenant::findOrFail($id);
        $oldStatus = $tenant->status;

        $tenant->update([
            'status' => $request->status,
        ]);

        // Log the status change
        activity()
            ->performedOn($tenant)
            ->causedBy(auth()->user())
            ->withProperties([
                'old_status' => $oldStatus,
                'new_status' => $request->status,
                'reason' => $request->reason,
            ])
            ->log('tenant_status_changed');

        return response()->json([
            'success' => true,
            'message' => 'Tenant status updated successfully',
            'data' => $tenant->fresh(),
        ]);
    }

    /**
     * Change tenant's subscription plan.
     */
    public function changeSubscription(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|uuid|exists:subscription_plans,id',
            'billing_cycle' => ['required', Rule::in(['monthly', 'annual'])],
            'starts_immediately' => 'boolean',
            'prorate' => 'boolean',
        ]);

        $tenant = Tenant::findOrFail($id);
        $newPlan = SubscriptionPlan::findOrFail($request->plan_id);
        $currentSubscription = $tenant->activeSubscription;

        try {
            DB::beginTransaction();

            // Cancel current subscription if it exists
            if ($currentSubscription) {
                $currentSubscription->update([
                    'status' => 'canceled',
                    'canceled_at' => now(),
                    'ends_at' => $request->starts_immediately ? now() : $currentSubscription->current_period_end,
                ]);
            }

            // Create new subscription
            $subscription = Subscription::create([
                'id' => Str::uuid(),
                'tenant_id' => $tenant->id,
                'plan_id' => $newPlan->id,
                'status' => $newPlan->trial_days > 0 ? 'trialing' : 'active',
                'billing_cycle' => $request->billing_cycle,
                'starts_at' => $request->starts_immediately ? now() : ($currentSubscription?->ends_at ?? now()),
                'trial_ends_at' => $newPlan->trial_days > 0 ? now()->addDays($newPlan->trial_days) : null,
                'current_period_start' => now(),
                'current_period_end' => $request->billing_cycle === 'annual' ? now()->addYear() : now()->addMonth(),
                'metadata' => [
                    'changed_by_admin' => true,
                    'admin_id' => auth()->id(),
                    'previous_plan_id' => $currentSubscription?->plan_id,
                    'prorate' => $request->prorate ?? false,
                ],
            ]);

            // Log the subscription change
            activity()
                ->performedOn($tenant)
                ->causedBy(auth()->user())
                ->withProperties([
                    'old_plan_id' => $currentSubscription?->plan_id,
                    'new_plan_id' => $newPlan->id,
                    'billing_cycle' => $request->billing_cycle,
                ])
                ->log('subscription_changed_by_admin');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Subscription changed successfully',
                'data' => [
                    'tenant' => $tenant->fresh(),
                    'subscription' => $subscription->load('plan'),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to change subscription',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get subscription history for a tenant.
     */
    public function subscriptionHistory(string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        $subscriptions = $tenant->subscriptions()
            ->with('plan')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'tenant' => $tenant,
                'subscriptions' => $subscriptions,
            ],
        ]);
    }

    /**
     * Extend tenant's trial period.
     */
    public function extendTrial(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'days' => 'required|integer|min:1|max:365',
            'reason' => 'nullable|string|max:500',
        ]);

        $tenant = Tenant::findOrFail($id);
        $subscription = $tenant->activeSubscription;

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription found for this tenant',
            ], 404);
        }

        $oldTrialEndsAt = $subscription->trial_ends_at;
        $newTrialEndsAt = ($oldTrialEndsAt ?? now())->addDays($request->days);

        $subscription->update([
            'trial_ends_at' => $newTrialEndsAt,
            'status' => 'trialing',
        ]);

        // Log the trial extension
        activity()
            ->performedOn($tenant)
            ->causedBy(auth()->user())
            ->withProperties([
                'old_trial_ends_at' => $oldTrialEndsAt,
                'new_trial_ends_at' => $newTrialEndsAt,
                'days_added' => $request->days,
                'reason' => $request->reason,
            ])
            ->log('trial_extended_by_admin');

        return response()->json([
            'success' => true,
            'message' => "Trial extended by {$request->days} days",
            'data' => $subscription->fresh(),
        ]);
    }

    /**
     * Get tenant statistics and analytics.
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_tenants' => Tenant::count(),
            'by_type' => [
                'personal' => Tenant::where('type', 'personal')->count(),
                'organization' => Tenant::where('type', 'organization')->count(),
            ],
            'by_status' => Tenant::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray(),
            'with_active_subscription' => Tenant::has('activeSubscription')->count(),
            'on_trial' => Subscription::where('status', 'trialing')
                ->where('trial_ends_at', '>', now())
                ->count(),
            'subscription_by_plan' => SubscriptionPlan::withCount([
                'subscriptions' => function ($query) {
                    $query->whereIn('status', ['trialing', 'active']);
                }
            ])
                ->get()
                ->map(function ($plan) {
                    return [
                        'id' => $plan->id,
                        'name' => $plan->name,
                        'type' => $plan->type,
                        'tier' => $plan->tier,
                        'active_subscriptions' => $plan->subscriptions_count,
                    ];
                }),
            'recent_signups' => Tenant::where('created_at', '>=', now()->subDays(30))
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Create a new tenant.
     *
     * @OA\Post(
     *     path="/api/v1/admin/tenants",
     *     summary="Create new tenant",
     *     description="Create a new tenant with optional subscription",
     *     operationId="adminCreateTenant",
     *     tags={"Admin - Tenants"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "type"},
     *             @OA\Property(property="name", type="string", example="Acme Corp"),
     *             @OA\Property(property="email", type="string", example="admin@acme.com"),
     *             @OA\Property(property="type", type="string", enum={"personal", "organization"}),
     *             @OA\Property(property="subdomain_preference", type="string", example="acme"),
     *             @OA\Property(property="plan_id", type="string", format="uuid"),
     *             @OA\Property(property="billing_cycle", type="string", enum={"monthly", "annual"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Tenant created successfully",
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
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:tenants,email',
            'type' => ['required', Rule::in(['personal', 'organization'])],
            'subdomain_preference' => 'nullable|string|alpha_dash|max:63|unique:tenants,subdomain_preference',
            'plan_id' => 'nullable|uuid|exists:subscription_plans,id',
            'billing_cycle' => ['nullable', Rule::in(['monthly', 'annual'])],
        ]);

        try {
            DB::beginTransaction();

            $tenant = Tenant::create([
                'id' => Str::uuid(),
                'name' => $request->name,
                'email' => $request->email,
                'type' => $request->type,
                'subdomain_preference' => $request->subdomain_preference,
                'status' => 'active',
                'setup_completed_at' => now(),
            ]);

            // Create subscription if plan is provided
            if ($request->plan_id) {
                $plan = SubscriptionPlan::findOrFail($request->plan_id);

                Subscription::create([
                    'id' => Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'plan_id' => $plan->id,
                    'status' => $plan->trial_days > 0 ? 'trialing' : 'active',
                    'billing_cycle' => $request->billing_cycle ?? 'monthly',
                    'starts_at' => now(),
                    'trial_ends_at' => $plan->trial_days > 0 ? now()->addDays($plan->trial_days) : null,
                    'current_period_start' => now(),
                    'current_period_end' => ($request->billing_cycle ?? 'monthly') === 'annual' ? now()->addYear() : now()->addMonth(),
                    'metadata' => [
                        'created_by_admin' => true,
                        'admin_id' => auth()->id(),
                    ],
                ]);
            }

            // Log the creation
            activity()
                ->performedOn($tenant)
                ->causedBy(auth()->user())
                ->log('tenant_created_by_admin');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tenant created successfully',
                'data' => $tenant->load('activeSubscription.plan'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create tenant',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update tenant information.
     *
     * @OA\Put(
     *     path="/api/v1/admin/tenants/{id}",
     *     summary="Update tenant",
     *     description="Update tenant information",
     *     operationId="adminUpdateTenant",
     *     tags={"Admin - Tenants"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="email", type="string"),
     *             @OA\Property(property="type", type="string", enum={"personal", "organization"}),
     *             @OA\Property(property="subdomain_preference", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tenant updated successfully",
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
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:tenants,email,' . $id,
            'type' => ['nullable', Rule::in(['personal', 'organization'])],
            'subdomain_preference' => 'nullable|string|alpha_dash|max:63|unique:tenants,subdomain_preference,' . $id,
        ]);

        $tenant = Tenant::findOrFail($id);

        try {
            $oldData = $tenant->toArray();

            $tenant->update($request->only([
                'name',
                'email',
                'type',
                'subdomain_preference',
            ]));

            // Log the update
            activity()
                ->performedOn($tenant)
                ->causedBy(auth()->user())
                ->withProperties([
                    'old' => $oldData,
                    'new' => $tenant->fresh()->toArray(),
                ])
                ->log('tenant_updated_by_admin');

            return response()->json([
                'success' => true,
                'message' => 'Tenant updated successfully',
                'data' => $tenant->fresh()->load('activeSubscription.plan'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update tenant',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Deactivate a tenant (soft deactivation, not deletion).
     *
     * @OA\Post(
     *     path="/api/v1/admin/tenants/{id}/deactivate",
     *     summary="Deactivate tenant",
     *     description="Deactivate a tenant and cancel their subscriptions",
     *     operationId="adminDeactivateTenant",
     *     tags={"Admin - Tenants"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="reason", type="string", example="Non-payment")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tenant deactivated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function deactivate(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $tenant = Tenant::findOrFail($id);

        if ($tenant->status === 'inactive') {
            return response()->json([
                'success' => false,
                'message' => 'Tenant is already deactivated',
            ], 400);
        }

        try {
            DB::beginTransaction();

            $oldStatus = $tenant->status;

            // Set tenant to inactive status
            $tenant->update([
                'status' => 'inactive',
            ]);

            // Cancel active subscriptions
            $tenant->subscriptions()
                ->whereIn('status', ['trialing', 'active'])
                ->update([
                    'status' => 'canceled',
                    'canceled_at' => now(),
                ]);

            // Log the deactivation
            activity()
                ->performedOn($tenant)
                ->causedBy(auth()->user())
                ->withProperties([
                    'old_status' => $oldStatus,
                    'new_status' => 'inactive',
                    'reason' => $request->reason,
                ])
                ->log('tenant_deactivated_by_admin');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tenant deactivated successfully',
                'data' => $tenant->fresh(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate tenant',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reactivate a deactivated tenant.
     *
     * @OA\Post(
     *     path="/api/v1/admin/tenants/{id}/reactivate",
     *     summary="Reactivate tenant",
     *     description="Reactivate a previously deactivated tenant",
     *     operationId="adminReactivateTenant",
     *     tags={"Admin - Tenants"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="reason", type="string", example="Payment received")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tenant reactivated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function reactivate(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $tenant = Tenant::findOrFail($id);

        if ($tenant->status === 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Tenant is already active',
            ], 400);
        }

        try {
            $oldStatus = $tenant->status;

            $tenant->update([
                'status' => 'active',
            ]);

            // Log the reactivation
            activity()
                ->performedOn($tenant)
                ->causedBy(auth()->user())
                ->withProperties([
                    'old_status' => $oldStatus,
                    'new_status' => 'active',
                    'reason' => $request->reason,
                ])
                ->log('tenant_reactivated_by_admin');

            return response()->json([
                'success' => true,
                'message' => 'Tenant reactivated successfully',
                'data' => $tenant->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reactivate tenant',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a tenant (hard delete - use with extreme caution).
     */
    public function destroy(string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        try {
            DB::beginTransaction();

            // Cancel active subscriptions
            $tenant->subscriptions()
                ->whereIn('status', ['trialing', 'active'])
                ->update([
                    'status' => 'canceled',
                    'canceled_at' => now(),
                ]);

            // Soft delete the tenant
            $tenant->delete();

            // Log the deletion
            activity()
                ->performedOn($tenant)
                ->causedBy(auth()->user())
                ->log('tenant_deleted_by_admin');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tenant deleted successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete tenant',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
