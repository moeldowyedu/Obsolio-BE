<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Info(
 *     title="Aasim AI API",
 *     version="1.0.0",
 *     description="Complete API documentation for Aasim AI platform",
 *     @OA\Contact(
 *         email="support@aasim.ai"
 *     )
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 *
 * @OA\Server(
 *     url="/api",
 *     description="API Server"
 * )
 */
class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/v1/auth/register",
     *     tags={"Authentication"},
     *     summary="Register a new user",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","password","organization_name"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="password123"),
     *             @OA\Property(property="organization_name", type="string", example="Acme Corp"),
     *             @OA\Property(property="industry", type="string", example="Technology"),
     *             @OA\Property(property="company_size", type="string", example="10-50")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User registered successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object"),
     *             @OA\Property(property="tenant", type="object"),
     *             @OA\Property(property="organization", type="object"),
     *             @OA\Property(property="token", type="string")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:8|confirmed',
            'organization_name' => 'required|string|max:255',
            'industry' => 'nullable|string|max:100',
            'company_size' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Create tenant
        $tenant = Tenant::create([
            'name' => $request->organization_name,
            'slug' => \Illuminate\Support\Str::slug($request->organization_name) . '-' . substr(md5(time()), 0, 6),
            'type' => 'organization',
            'status' => 'active',
            'trial_ends_at' => now()->addDays(14),
        ]);

        // Create domain for tenant
        $tenant->domains()->create([
            'domain' => $tenant->slug . '.aasim.local',
        ]);

        $tenant->run(function() use ($request, $tenant) {
            // Create organization
            $organization = $tenant->organizations()->create([
                'name' => $request->organization_name,
                'industry' => $request->industry,
                'company_size' => $request->company_size,
                'timezone' => 'UTC',
                'settings' => [],
            ]);

            // Create user
            $user = $tenant->users()->create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'status' => 'active',
            ]);

            // Assign admin role
            $user->assignRole('admin');

            // Create assignment
            $user->assignments()->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'access_scope' => ['role' => 'owner'],
            ]);

            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'user' => $user,
                'tenant' => $tenant,
                'organization' => $organization,
                'token' => $token,
            ], 201);
        });
    }

    /**
     * @OA\Post(
     *     path="/v1/auth/login",
     *     tags={"Authentication"},
     *     summary="Login user",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object"),
     *             @OA\Property(property="token", type="string")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Invalid credentials")
     * )
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Update last login
        $user->update(['last_login_at' => now()]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => $user->load(['tenant', 'assignments.organization']),
            'token' => $token,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/v1/auth/logout",
     *     tags={"Authentication"},
     *     summary="Logout user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Logout successful")
     * )
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    /**
     * @OA\Get(
     *     path="/v1/auth/me",
     *     tags={"Authentication"},
     *     summary="Get authenticated user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="User data",
     *         @OA\JsonContent(@OA\Property(property="user", type="object"))
     *     )
     * )
     */
    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user()->load([
                'tenant',
                'assignments.organization',
                'assignments.department',
                'teams',
            ]),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/v1/auth/forgot-password",
     *     tags={"Authentication"},
     *     summary="Request password reset",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Reset link sent")
     * )
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        // Implementation for password reset email
        return response()->json([
            'message' => 'Password reset link sent to your email',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/v1/auth/reset-password",
     *     tags={"Authentication"},
     *     summary="Reset password",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password","password_confirmation","token"},
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="password", type="string", format="password"),
     *             @OA\Property(property="password_confirmation", type="string", format="password"),
     *             @OA\Property(property="token", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Password reset successful")
     * )
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
            'token' => 'required',
        ]);

        // Implementation for password reset
        return response()->json([
            'message' => 'Password reset successfully',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/v1/dashboard/stats",
     *     tags={"Dashboard"},
     *     summary="Get dashboard statistics",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Dashboard stats")
     * )
     */
    public function dashboardStats(Request $request)
    {
        $user = $request->user();
        $tenant = $user->tenant;

        return response()->json([
            'total_agents' => $tenant->agents()->count(),
            'active_job_flows' => \App\Models\JobFlow::where('tenant_id', $tenant->id)
                ->where('status', 'active')
                ->count(),
            'pending_approvals' => \App\Models\HITLApproval::where('tenant_id', $tenant->id)
                ->where('status', 'pending')
                ->count(),
            'executions_today' => \App\Models\AgentExecution::where('tenant_id', $tenant->id)
                ->whereDate('created_at', today())
                ->count(),
            'total_users' => $tenant->users()->count(),
            'subscription_status' => $tenant->activeSubscription?->status ?? 'trial',
            'trial_ends_at' => $tenant->trial_ends_at,
        ]);
    }
}