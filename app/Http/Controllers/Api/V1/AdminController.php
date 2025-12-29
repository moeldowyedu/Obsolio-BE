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
     *
     * @OA\Get(
     *     path="/api/v1/admin/users",
     *     summary="List all users",
     *     description="Get a paginated list of all users with filters",
     *     tags={"Admin - Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by name or email",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="role",
     *         in="query",
     *         description="Filter by role",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by verification status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"verified", "unverified"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Users retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/PaginatedResponse")
     *         )
     *     ),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
     *     @OA\Response(response=403, ref="#/components/responses/Forbidden")
     * )
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
     *
     * @OA\Get(
     *     path="/api/v1/admin/users/{id}",
     *     summary="Get user details",
     *     description="Get detailed information about a specific user",
     *     tags={"Admin - Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="User ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="User not found")
     * )
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
     * Create a new user.
     *
     * @OA\Post(
     *     path="/api/v1/admin/users",
     *     summary="Create new user",
     *     description="Create a new user with role assignment",
     *     operationId="adminCreateUser",
     *     tags={"Admin - Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "password", "role"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", example="john@example.com"),
     *             @OA\Property(property="password", type="string", example="password123"),
     *             @OA\Property(property="tenant_id", type="string", format="uuid"),
     *             @OA\Property(property="role", type="string", enum={"system_admin", "tenant_admin", "user"}),
     *             @OA\Property(property="email_verified", type="boolean")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function createUser(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'tenant_id' => 'nullable|uuid|exists:tenants,id',
            'role' => 'required|in:system_admin,tenant_admin,user',
            'email_verified' => 'boolean',
        ]);

        try {
            $user = User::create([
                'id' => Str::uuid(),
                'name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt($request->password),
                'tenant_id' => $request->tenant_id,
                'email_verified_at' => $request->email_verified ? now() : null,
            ]);

            // Assign role
            $user->assignRole($request->role);

            // Log the creation
            activity()
                ->performedOn($user)
                ->causedBy(auth()->user())
                ->log('user_created_by_admin');

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
                'data' => $user->load('roles'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update user information.
     *
     * @OA\Put(
     *     path="/api/v1/admin/users/{id}",
     *     summary="Update user",
     *     description="Update user information and role",
     *     operationId="adminUpdateUser",
     *     tags={"Admin - Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="email", type="string"),
     *             @OA\Property(property="password", type="string"),
     *             @OA\Property(property="role", type="string", enum={"system_admin", "tenant_admin", "user"}),
     *             @OA\Property(property="email_verified", type="boolean")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function updateUser(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $id,
            'password' => 'nullable|string|min:8',
            'role' => 'nullable|in:system_admin,tenant_admin,user',
            'email_verified' => 'nullable|boolean',
        ]);

        $user = User::findOrFail($id);

        try {
            $oldData = $user->toArray();

            // Update basic fields
            $updateData = [];
            if ($request->filled('name')) {
                $updateData['name'] = $request->name;
            }
            if ($request->filled('email')) {
                $updateData['email'] = $request->email;
            }
            if ($request->filled('password')) {
                $updateData['password'] = bcrypt($request->password);
            }
            if ($request->has('email_verified')) {
                $updateData['email_verified_at'] = $request->email_verified ? now() : null;
            }

            if (!empty($updateData)) {
                $user->update($updateData);
            }

            // Update role if provided
            if ($request->filled('role')) {
                $user->syncRoles([$request->role]);
            }

            // Log the update
            activity()
                ->performedOn($user)
                ->causedBy(auth()->user())
                ->withProperties([
                    'old' => $oldData,
                    'new' => $user->fresh()->toArray(),
                ])
                ->log('user_updated_by_admin');

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'data' => $user->fresh()->load('roles'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a user.
     *
     * @OA\Delete(
     *     path="/api/v1/admin/users/{id}",
     *     summary="Delete user",
     *     description="Delete a user (system admins cannot be deleted)",
     *     tags={"Admin - Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="User ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Cannot delete system admin"),
     *     @OA\Response(response=404, description="User not found")
     * )
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
     *
     * @OA\Get(
     *     path="/api/v1/admin/subscription-plans",
     *     summary="List subscription plans",
     *     description="Get all subscription plans with optional filters",
     *     tags={"Admin - Subscription Plans"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filter by plan type",
     *         required=false,
     *         @OA\Schema(type="string", enum={"personal", "organization"})
     *     ),
     *     @OA\Parameter(
     *         name="active",
     *         in="query",
     *         description="Filter by active status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"true", "false"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Plans retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
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
     *
     * @OA\Post(
     *     path="/api/v1/admin/subscription-plans",
     *     summary="Create subscription plan",
     *     description="Create a new subscription plan",
     *     tags={"Admin - Subscription Plans"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "type", "tier", "max_users", "max_agents", "storage_gb", "trial_days"},
     *             @OA\Property(property="name", type="string", example="Pro Plan"),
     *             @OA\Property(property="type", type="string", enum={"personal", "organization"}),
     *             @OA\Property(property="tier", type="string", enum={"free", "pro", "team", "business", "enterprise"}),
     *             @OA\Property(property="price_monthly", type="number", format="float", example=29.99),
     *             @OA\Property(property="price_annual", type="number", format="float", example=299.99),
     *             @OA\Property(property="max_users", type="integer", example=5),
     *             @OA\Property(property="max_agents", type="integer", example=10),
     *             @OA\Property(property="storage_gb", type="integer", example=100),
     *             @OA\Property(property="trial_days", type="integer", example=14),
     *             @OA\Property(property="features", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="limits", type="object"),
     *             @OA\Property(property="description", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Plan created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
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
     *
     * @OA\Put(
     *     path="/api/v1/admin/subscription-plans/{id}",
     *     summary="Update subscription plan",
     *     description="Update an existing subscription plan",
     *     tags={"Admin - Subscription Plans"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Plan ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="price_monthly", type="number", format="float"),
     *             @OA\Property(property="price_annual", type="number", format="float"),
     *             @OA\Property(property="max_users", type="integer"),
     *             @OA\Property(property="max_agents", type="integer"),
     *             @OA\Property(property="storage_gb", type="integer"),
     *             @OA\Property(property="trial_days", type="integer"),
     *             @OA\Property(property="features", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="limits", type="object"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="is_active", type="boolean")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Plan updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Plan not found")
     * )
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
     *
     * @OA\Delete(
     *     path="/api/v1/admin/subscription-plans/{id}",
     *     summary="Delete subscription plan",
     *     description="Delete a subscription plan (only if no active subscriptions)",
     *     tags={"Admin - Subscription Plans"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Plan ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Plan deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Plan has active subscriptions"),
     *     @OA\Response(response=404, description="Plan not found")
     * )
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
     *
     * @OA\Get(
     *     path="/api/v1/admin/agents",
     *     summary="List all agents",
     *     description="Get a paginated list of all agents with filters",
     *     tags={"Admin - Agents"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="category",
     *         in="query",
     *         description="Filter by category",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="active",
     *         in="query",
     *         description="Filter by active status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"true", "false"})
     *     ),
     *     @OA\Parameter(
     *         name="marketplace",
     *         in="query",
     *         description="Filter by marketplace status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"true", "false"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Agents retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/PaginatedResponse")
     *         )
     *     )
     * )
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
     *     path="/api/v1/admin/agents/{id}",
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
     *
     * @OA\Post(
     *     path="/api/v1/admin/agents",
     *     summary="Create new agent",
     *     description="Create a new agent in the system",
     *     tags={"Admin - Agents"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "slug", "category", "price_model"},
     *             @OA\Property(property="name", type="string", example="Data Analyzer"),
     *             @OA\Property(property="slug", type="string", example="data-analyzer"),
     *             @OA\Property(property="category", type="string", example="analytics"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="long_description", type="string"),
     *             @OA\Property(property="icon_url", type="string", format="url"),
     *             @OA\Property(property="banner_url", type="string", format="url"),
     *             @OA\Property(property="capabilities", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="supported_languages", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="price_model", type="string", enum={"free", "one_time", "subscription", "usage_based"}),
     *             @OA\Property(property="base_price", type="number"),
     *             @OA\Property(property="monthly_price", type="number"),
     *             @OA\Property(property="annual_price", type="number"),
     *             @OA\Property(property="is_marketplace", type="boolean"),
     *             @OA\Property(property="is_featured", type="boolean")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Agent created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
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
     *
     * @OA\Put(
     *     path="/api/v1/admin/agents/{id}",
     *     summary="Update agent",
     *     description="Update an existing agent",
     *     tags={"Admin - Agents"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Agent ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="long_description", type="string"),
     *             @OA\Property(property="icon_url", type="string", format="url"),
     *             @OA\Property(property="banner_url", type="string", format="url"),
     *             @OA\Property(property="capabilities", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="price_model", type="string", enum={"free", "one_time", "subscription", "usage_based"}),
     *             @OA\Property(property="base_price", type="number"),
     *             @OA\Property(property="monthly_price", type="number"),
     *             @OA\Property(property="annual_price", type="number"),
     *             @OA\Property(property="is_active", type="boolean"),
     *             @OA\Property(property="is_featured", type="boolean"),
     *             @OA\Property(property="is_marketplace", type="boolean")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Agent updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Agent not found")
     * )
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
     *
     * @OA\Delete(
     *     path="/api/v1/admin/agents/{id}",
     *     summary="Delete agent",
     *     description="Soft delete an agent",
     *     tags={"Admin - Agents"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Agent ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Agent deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Agent not found")
     * )
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
     *
     * @OA\Get(
     *     path="/api/v1/admin/analytics/overview",
     *     summary="Get analytics overview",
     *     description="Get overall platform statistics and metrics",
     *     tags={"Admin - Analytics"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Analytics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_tenants", type="integer"),
     *                 @OA\Property(property="active_tenants", type="integer"),
     *                 @OA\Property(property="total_users", type="integer"),
     *                 @OA\Property(property="total_subscriptions", type="integer"),
     *                 @OA\Property(property="total_revenue_monthly", type="number"),
     *                 @OA\Property(property="total_agents", type="integer"),
     *                 @OA\Property(property="active_agents", type="integer"),
     *                 @OA\Property(property="featured_agents", type="integer")
     *             )
     *         )
     *     )
     * )
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
     *
     * @OA\Get(
     *     path="/api/v1/admin/analytics/revenue",
     *     summary="Get revenue analytics",
     *     description="Get revenue breakdown by period",
     *     tags={"Admin - Analytics"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="period",
     *         in="query",
     *         description="Time period for analytics",
     *         required=false,
     *         @OA\Schema(type="string", enum={"day", "week", "month", "year"}, default="month")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Revenue analytics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="summary",
     *                     type="object",
     *                     @OA\Property(property="total_revenue", type="number"),
     *                     @OA\Property(property="total_invoices", type="integer"),
     *                     @OA\Property(property="average_invoice", type="number"),
     *                     @OA\Property(property="period", type="string")
     *                 ),
     *                 @OA\Property(property="daily_breakdown", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     )
     * )
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
     *
     * @OA\Get(
     *     path="/api/v1/admin/analytics/agents",
     *     summary="Get agent analytics",
     *     description="Get agent statistics by category and top agents",
     *     tags={"Admin - Analytics"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Agent analytics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="by_category", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="top_agents", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     )
     * )
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
     *
     * @OA\Get(
     *     path="/api/v1/admin/activity-logs",
     *     summary="Get activity logs",
     *     description="Get paginated list of platform activity logs",
     *     tags={"Admin - Audit Logs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         description="Filter by user ID",
     *         required=false,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="action",
     *         in="query",
     *         description="Filter by action type",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Activity logs retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/PaginatedResponse")
     *         )
     *     )
     * )
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
     *
     * @OA\Get(
     *     path="/api/v1/admin/impersonation-logs",
     *     summary="Get impersonation logs",
     *     description="Get paginated list of impersonation activity",
     *     tags={"Admin - Audit Logs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Impersonation logs retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/PaginatedResponse")
     *         )
     *     )
     * )
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

    // =========================================================================
    // CONSOLE PERMISSIONS & ROLES
    // =========================================================================

    /**
     * @OA\Get(
     *     path="/api/v1/admin/permissions",
     *     tags={"Admin - Permissions & Roles"},
     *     summary="List all console permissions",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of console permissions"
     *     )
     * )
     */
    public function listPermissions(Request $request): JsonResponse
    {
        try {
            // Get all console-scoped permissions
            $permissions = \Spatie\Permission\Models\Permission::where('guard_name', 'console')
                ->orderBy('name')
                ->get(['id', 'name']);

            // Group permissions by prefix
            $grouped = $permissions->groupBy(function ($permission) {
                $parts = explode('.', $permission->name);
                return $parts[0] ?? 'other';
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'all' => $permissions,
                    'grouped' => $grouped,
                    'total' => $permissions->count(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve permissions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/roles",
     *     tags={"Admin - Permissions & Roles"},
     *     summary="List all console roles",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of console roles"
     *     )
     * )
     */
    public function listRoles(Request $request): JsonResponse
    {
        try {
            // Get all console-scoped roles
            $roles = \Spatie\Permission\Models\Role::where('guard_name', 'console')
                ->whereNull('tenant_id')
                ->with(['permissions:id,name'])
                ->get();

            return response()->json([
                'success' => true,
                'data' => $roles->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'guard_name' => $role->guard_name,
                        'permissions' => $role->permissions->pluck('name'),
                        'permissions_count' => $role->permissions->count(),
                        'created_at' => $role->created_at,
                    ];
                }),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve roles',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}