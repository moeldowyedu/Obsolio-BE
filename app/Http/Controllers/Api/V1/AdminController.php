<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Tenant;
use App\Models\SubscriptionPlan;
use App\Models\Subscription;
use App\Models\Agent;
use App\Models\BillingInvoice;
use App\Models\ActivityLog;
use App\Models\ImpersonationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    // =========================================================================
    // USER MANAGEMENT
    // =========================================================================

    /**
     * List all users with pagination and filters.
     */
    public function listUsers(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $role = $request->query('role');
        $status = $request->query('status');

        $users = User::query()
            ->when($search, function ($query, $search) {
                return $query->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('email', 'ILIKE', "%{$search}%");
            })
            ->when($role, function ($query, $role) {
                return $query->role($role);
            })
            ->when($status, function ($query, $status) {
                if ($status === 'verified') {
                    return $query->whereNotNull('email_verified_at');
                } elseif ($status === 'unverified') {
                    return $query->whereNull('email_verified_at');
                }
            })
            ->with('roles')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Get specific user details.
     */
    public function getUser(string $id): JsonResponse
    {
        $user = User::with(['roles', 'permissions'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }

    /**
     * Delete a user.
     */
    public function deleteUser(string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        // Prevent deleting system admins
        if ($user->hasRole('system_admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete system admin users',
            ], 403);
        }

        try {
            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================================
    // SUBSCRIPTION PLANS MANAGEMENT
    // =========================================================================

    /**
     * List all subscription plans.
     */
    public function listPlans(Request $request): JsonResponse
    {
        $type = $request->query('type');
        $active = $request->query('active');

        $plans = SubscriptionPlan::query()
            ->when($type, function ($query, $type) {
                return $query->where('type', $type);
            })
            ->when($active !== null, function ($query) use ($active) {
                return $query->where('is_active', $active === 'true');
            })
            ->orderBy('type')
            ->orderBy('price_monthly')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $plans,
        ]);
    }

    /**
     * Create a new subscription plan.
     */
    public function createPlan(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|in:personal,organization',
            'tier' => 'required|in:free,pro,team,business,enterprise',
            'price_monthly' => 'nullable|numeric|min:0',
            'price_annual' => 'nullable|numeric|min:0',
            'max_users' => 'required|integer|min:1',
            'max_agents' => 'required|integer|min:0',
            'storage_gb' => 'required|integer|min:1',
            'trial_days' => 'required|integer|min:0',
            'features' => 'nullable|array',
            'limits' => 'nullable|array',
            'description' => 'nullable|string',
        ]);

        try {
            $plan = SubscriptionPlan::create([
                'id' => Str::uuid(),
                'name' => $request->name,
                'type' => $request->type,
                'tier' => $request->tier,
                'price_monthly' => $request->price_monthly,
                'price_annual' => $request->price_annual,
                'max_users' => $request->max_users,
                'max_agents' => $request->max_agents,
                'storage_gb' => $request->storage_gb,
                'trial_days' => $request->trial_days,
                'features' => $request->features ?? [],
                'limits' => $request->limits ?? [],
                'description' => $request->description,
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Subscription plan created successfully',
                'data' => $plan,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create plan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a subscription plan.
     */
    public function updatePlan(Request $request, string $id): JsonResponse
    {
        $plan = SubscriptionPlan::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:100',
            'price_monthly' => 'sometimes|nullable|numeric|min:0',
            'price_annual' => 'sometimes|nullable|numeric|min:0',
            'max_users' => 'sometimes|integer|min:1',
            'max_agents' => 'sometimes|integer|min:0',
            'storage_gb' => 'sometimes|integer|min:1',
            'trial_days' => 'sometimes|integer|min:0',
            'features' => 'sometimes|array',
            'limits' => 'sometimes|array',
            'description' => 'sometimes|nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            $plan->update($request->only([
                'name',
                'price_monthly',
                'price_annual',
                'max_users',
                'max_agents',
                'storage_gb',
                'trial_days',
                'features',
                'limits',
                'description',
                'is_active',
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Plan updated successfully',
                'data' => $plan->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update plan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a subscription plan.
     */
    public function deletePlan(string $id): JsonResponse
    {
        $plan = SubscriptionPlan::findOrFail($id);

        // Check if plan has active subscriptions
        $activeSubscriptions = Subscription::where('plan_id', $id)
            ->whereIn('status', ['trialing', 'active'])
            ->count();

        if ($activeSubscriptions > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete plan with {$activeSubscriptions} active subscriptions",
            ], 400);
        }

        try {
            $plan->delete();

            return response()->json([
                'success' => true,
                'message' => 'Plan deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete plan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================================
    // AGENTS MANAGEMENT
    // =========================================================================

    /**
     * List all agents.
     */
    public function listAgents(Request $request): JsonResponse
    {
        $category = $request->query('category');
        $active = $request->query('active');
        $marketplace = $request->query('marketplace');

        $agents = Agent::query()
            ->when($category, function ($query, $category) {
                return $query->where('category', $category);
            })
            ->when($active !== null, function ($query) use ($active) {
                return $query->where('is_active', $active === 'true');
            })
            ->when($marketplace !== null, function ($query) use ($marketplace) {
                return $query->where('is_marketplace', $marketplace === 'true');
            })
            ->withTrashed()
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $agents,
        ]);
    }

    /**
     * Get specific agent details.
     *
     * @OA\Get(
     *     path="/v1/admin/agents/{id}",
     *     summary="Get agent details",
     *     description="Get detailed information about a specific agent (admin view)",
     *     operationId="adminGetAgent",
     *     tags={"Admin - Agents"},
     *     security={{"bearerAuth":{}}},
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
    public function getAgent(string $id): JsonResponse
    {
        $agent = Agent::withTrashed()
            ->with(['category', 'endpoints'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $agent,
        ]);
    }

    /**
     * Create a new agent.
     */
    public function createAgent(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|unique:agents,slug',
            'category' => 'required|string|max:100',
            'description' => 'nullable|string',
            'long_description' => 'nullable|string',
            'icon_url' => 'nullable|url',
            'banner_url' => 'nullable|url',
            'capabilities' => 'nullable|array',
            'supported_languages' => 'nullable|array',
            'price_model' => 'required|in:free,one_time,subscription,usage_based',
            'base_price' => 'nullable|numeric|min:0',
            'monthly_price' => 'nullable|numeric|min:0',
            'annual_price' => 'nullable|numeric|min:0',
            'is_marketplace' => 'boolean',
            'is_featured' => 'boolean',
        ]);

        try {
            $agent = Agent::create([
                'id' => Str::uuid(),
                'name' => $request->name,
                'slug' => $request->slug,
                'category' => $request->category,
                'description' => $request->description,
                'long_description' => $request->long_description,
                'icon_url' => $request->icon_url,
                'banner_url' => $request->banner_url,
                'capabilities' => $request->capabilities ?? [],
                'supported_languages' => $request->supported_languages ?? ['en'],
                'price_model' => $request->price_model,
                'base_price' => $request->base_price ?? 0,
                'monthly_price' => $request->monthly_price,
                'annual_price' => $request->annual_price,
                'is_marketplace' => $request->is_marketplace ?? true,
                'is_active' => true,
                'is_featured' => $request->is_featured ?? false,
                'version' => '1.0.0',
                'created_by_user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Agent created successfully',
                'data' => $agent,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create agent',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an agent.
     */
    public function updateAgent(Request $request, string $id): JsonResponse
    {
        $agent = Agent::withTrashed()->findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'long_description' => 'sometimes|nullable|string',
            'icon_url' => 'sometimes|nullable|url',
            'banner_url' => 'sometimes|nullable|url',
            'capabilities' => 'sometimes|array',
            'price_model' => 'sometimes|in:free,one_time,subscription,usage_based',
            'base_price' => 'sometimes|nullable|numeric|min:0',
            'monthly_price' => 'sometimes|nullable|numeric|min:0',
            'annual_price' => 'sometimes|nullable|numeric|min:0',
            'is_active' => 'sometimes|boolean',
            'is_featured' => 'sometimes|boolean',
            'is_marketplace' => 'sometimes|boolean',
        ]);

        try {
            $agent->update($request->only([
                'name',
                'description',
                'long_description',
                'icon_url',
                'banner_url',
                'capabilities',
                'price_model',
                'base_price',
                'monthly_price',
                'annual_price',
                'is_active',
                'is_featured',
                'is_marketplace',
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Agent updated successfully',
                'data' => $agent->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update agent',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete (soft delete) an agent.
     */
    public function deleteAgent(string $id): JsonResponse
    {
        $agent = Agent::findOrFail($id);

        try {
            $agent->delete();

            return response()->json([
                'success' => true,
                'message' => 'Agent deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete agent',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk activate agents.
     *
     * @OA\Post(
     *     path="/api/v1/admin/agents/bulk-activate",
     *     summary="Bulk activate agents",
     *     description="Activate multiple agents at once",
     *     operationId="adminBulkActivateAgents",
     *     tags={"Admin - Agents"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"agent_ids"},
     *             @OA\Property(property="agent_ids", type="array", @OA\Items(type="string", format="uuid"), example={"uuid1", "uuid2"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Agents activated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="activated_count", type="integer")
     *         )
     *     )
     * )
     */
    public function bulkActivateAgents(Request $request): JsonResponse
    {
        $request->validate([
            'agent_ids' => 'required|array',
            'agent_ids.*' => 'required|uuid|exists:agents,id',
        ]);

        try {
            $count = Agent::whereIn('id', $request->agent_ids)
                ->update(['is_active' => true]);

            return response()->json([
                'success' => true,
                'message' => "{$count} agent(s) activated successfully",
                'activated_count' => $count,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to activate agents',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk deactivate agents.
     *
     * @OA\Post(
     *     path="/api/v1/admin/agents/bulk-deactivate",
     *     summary="Bulk deactivate agents",
     *     description="Deactivate multiple agents at once",
     *     operationId="adminBulkDeactivateAgents",
     *     tags={"Admin - Agents"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"agent_ids"},
     *             @OA\Property(property="agent_ids", type="array", @OA\Items(type="string", format="uuid"), example={"uuid1", "uuid2"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Agents deactivated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="deactivated_count", type="integer")
     *         )
     *     )
     * )
     */
    public function bulkDeactivateAgents(Request $request): JsonResponse
    {
        $request->validate([
            'agent_ids' => 'required|array',
            'agent_ids.*' => 'required|uuid|exists:agents,id',
        ]);

        try {
            $count = Agent::whereIn('id', $request->agent_ids)
                ->update(['is_active' => false]);

            return response()->json([
                'success' => true,
                'message' => "{$count} agent(s) deactivated successfully",
                'deactivated_count' => $count,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate agents',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================================
    // ANALYTICS & REPORTS
    // =========================================================================

    /**
     * Get analytics overview.
     */
    public function analyticsOverview(): JsonResponse
    {
        $stats = [
            'total_tenants' => Tenant::count(),
            'active_tenants' => Tenant::where('status', 'active')->count(),
            'total_users' => User::count(),
            'total_subscriptions' => Subscription::whereIn('status', ['trialing', 'active'])->count(),
            'total_revenue_monthly' => BillingInvoice::where('status', 'paid')
                ->whereMonth('paid_at', now()->month)
                ->sum('total'),
            'total_agents' => Agent::count(),
            'active_agents' => Agent::where('is_active', true)->count(),
            'featured_agents' => Agent::where('is_featured', true)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get revenue analytics.
     */
    public function revenueAnalytics(Request $request): JsonResponse
    {
        $period = $request->query('period', 'month'); // day, week, month, year

        $startDate = match ($period) {
            'day' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            default => now()->startOfMonth(),
        };

        $revenue = BillingInvoice::where('status', 'paid')
            ->where('paid_at', '>=', $startDate)
            ->selectRaw('DATE(paid_at) as date, SUM(total) as total, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $summary = [
            'total_revenue' => $revenue->sum('total'),
            'total_invoices' => $revenue->sum('count'),
            'average_invoice' => $revenue->avg('total'),
            'period' => $period,
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'daily_breakdown' => $revenue,
            ],
        ]);
    }

    /**
     * Get agent analytics.
     */
    public function agentAnalytics(): JsonResponse
    {
        $stats = Agent::select('category')
            ->selectRaw('COUNT(*) as total_agents')
            ->selectRaw('SUM(total_installs) as total_installs')
            ->selectRaw('AVG(rating) as average_rating')
            ->groupBy('category')
            ->get();

        $topAgents = Agent::orderBy('total_installs', 'desc')
            ->limit(10)
            ->get(['id', 'name', 'category', 'total_installs', 'rating']);

        return response()->json([
            'success' => true,
            'data' => [
                'by_category' => $stats,
                'top_agents' => $topAgents,
            ],
        ]);
    }

    // =========================================================================
    // ACTIVITY LOGS
    // =========================================================================

    /**
     * Get activity logs.
     */
    public function activityLogs(Request $request): JsonResponse
    {
        $userId = $request->query('user_id');
        $action = $request->query('action');

        $logs = ActivityLog::query()
            ->when($userId, function ($query, $userId) {
                return $query->where('user_id', $userId);
            })
            ->when($action, function ($query, $action) {
                return $query->where('action', $action);
            })
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Get impersonation logs.
     */
    public function impersonationLogs(Request $request): JsonResponse
    {
        $logs = ImpersonationLog::with(['admin', 'targetUser'])
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }
}