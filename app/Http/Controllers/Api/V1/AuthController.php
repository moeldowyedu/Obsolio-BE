<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\UserActivity;
use App\Models\UserSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/auth/register",
     *     summary="Register new user with tenant",
     *     operationId="register",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *      *             required={"type","fullName","email","password","country","phone","subdomain"},
     *             @OA\Property(property="type", type="string", enum={"personal", "organization"}, example="personal", description="Account type"),
     *             @OA\Property(property="fullName", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="SecurePass123!"),
     *             @OA\Property(property="phone", type="string", example="+1234567890", description="Phone with country code"),
     *             @OA\Property(property="country", type="string", example="USA"),
     *             @OA\Property(property="subdomain", type="string", example="my-workspace", description="Required for ALL types. Unique workspace URL identifier."),
     *             @OA\Property(property="organizationFullName", type="string", example="Acme Corporation", description="Required if type=organization"),
     *             @OA\Property(property="organizationShortName", type="string", example="acme", description="Optional display short name"),
     *             @OA\Property(property="organizationLogo", type="string", example="https://example.com/logo.png", description="Optional")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Registration successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Registration successful"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john.doe@example.com"),
     *                     @OA\Property(property="phone", type="string", example="+1234567890"),
     *                     @OA\Property(property="country", type="string", example="USA"),
     *                     @OA\Property(property="status", type="string", example="active")
     *                 ),
     *                 @OA\Property(property="tenant", type="object",
     *                     @OA\Property(property="id", type="string", example="john-doe-abc1"),
     *                     @OA\Property(property="name", type="string", example="John Doe's Workspace"),
     *                     @OA\Property(property="type", type="string", example="personal"),
     *                     @OA\Property(property="short_name", type="string", example="john-doe-abc1")
     *                 ),
     *                 @OA\Property(property="token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."),
     *                 @OA\Property(property="token_type", type="string", example="bearer"),
     *                 @OA\Property(property="expires_in", type="integer", example=3600),
     *                 @OA\Property(property="workspace_url", type="string", example="https://john-doe-abc1.obsolio.com")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function register(Request $request): JsonResponse
    {
        // 1. Validation
        $rules = [
            'type' => 'required|in:personal,organization',
            'fullName' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'country' => 'required|string|max:100',
            'phone' => 'required|string|max:20', // With country code
            // Subdomain is REQUIRED for ALL types and MUST BE UNIQUE
            'subdomain' => 'required|string|max:63|regex:/^[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?$/|unique:tenants,id',
        ];

        if ($request->type === 'organization') {
            $rules['organizationFullName'] = 'required|string|max:255';
            $rules['organizationShortName'] = 'nullable|string|max:50'; // Just a display short name, not unique
            $rules['organizationLogo'] = 'nullable|string|max:500'; // URL or path
        }

        $request->validate($rules);

        try {
            return DB::transaction(function () use ($request) {
                // Step 1: Create Tenant (Workspace)
                $tenantData = $this->prepareTenantData($request);
                $tenant = Tenant::create($tenantData);

                // Create Domain for Tenant (Workspace Subdomain)
                // The 'id' of the tenant IS the shortname/subdomain now (from prepareTenantData)
                $subdomain = $tenant->id;
                $tenant->domains()->create(['domain' => $subdomain . '.' . (config('tenancy.central_domains')[0] ?? 'obsolio.com')]);


                // Step 2: Create Organization (if applicable)
                if ($request->type === 'organization') {
                    $tenant->organizations()->create([
                        'name' => $request->organizationFullName,
                        'short_name' => $request->organizationShortName,
                        'country' => $request->country,
                        'phone' => $request->phone,
                        'logo_url' => $request->organizationLogo,
                        // Defaults or other fields can be set here
                    ]);
                }

                // Step 3: Create User
                $user = User::create([
                    'tenant_id' => $tenant->id,
                    'name' => $request->fullName,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'phone' => $request->phone,
                    'country' => $request->country,
                    'status' => 'active',
                    'trial_ends_at' => now()->addDays(7),
                ]);

                // Step 4: Create Membership with owner role
                TenantMembership::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'role' => TenantMembership::ROLE_OWNER,
                    'joined_at' => now(),
                ]);

                // Step 5: Generate JWT token
                $token = JWTAuth::fromUser($user);

                // Step 6: Create session
                $sessionId = Str::uuid()->toString();
                UserSession::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'session_id' => $sessionId,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'device_type' => $this->detectDeviceType($request->userAgent()),
                    'browser' => $this->detectBrowser($request->userAgent()),
                    'platform' => $this->detectPlatform($request->userAgent()),
                    'location' => $this->detectLocation($request->ip()),
                    'started_at' => now(),
                    'last_activity_at' => now(),
                    'is_active' => true,
                ]);

                // Step 7: Log activity
                $this->logActivity(
                    $user->id,
                    'registration',
                    'create',
                    'User',
                    $user->id,
                    "User registered: {$user->email} ({$request->type})",
                    $request,
                    'success',
                    null,
                    false,
                    $tenant->id
                );

                // Step 8: Return success
                return response()->json([
                    'success' => true,
                    'message' => 'Registration successful',
                    'data' => [
                        'user' => $user->fresh(), // Reload to get all fields
                        'tenant' => $tenant,
                        'token' => $token,
                        'token_type' => 'bearer',
                        'expires_in' => (int) config('jwt.ttl') * 60,
                        'workspace_url' => 'https://' . $subdomain . '.' . (config('tenancy.central_domains')[0] ?? 'obsolio.com')
                    ],
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Prepare tenant data based on account type.
     */
    private function prepareTenantData(Request $request): array
    {
        if ($request->type === 'personal') {
            // Personal tenant
            $name = $request->fullName . "'s Workspace";

            // Use validated subdomain from request
            $id = $request->subdomain;

            return [
                'id' => $id, // ID IS the subdomain
                'name' => $name,
                'short_name' => $id, // For personal, short_name can be same as ID
                'type' => 'personal',
                'status' => 'active',
                'trial_ends_at' => now()->addDays(7),
                'setup_completed' => true,
                'setup_completed_at' => now(),
            ];
        } else {
            // Organization
            $name = $request->organizationFullName;
            $shortName = $request->organizationShortName;

            // Subdomain is now explicitly passed and validated as unique (in register validation)
            $id = $request->subdomain;

            return [
                'id' => $id, // ID IS the subdomain
                'name' => $name,
                'short_name' => $shortName, // Allow non-unique short name
                'organization_name' => $name,
                'type' => 'organization',
                'status' => 'active',
                'trial_ends_at' => now()->addDays(7),
                'setup_completed' => true,
                'setup_completed_at' => now(),
            ];
        }
    }

    /**
     * @OA\Post(
     *     path="/auth/login",
     *     summary="Login user",
     *     operationId="login",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="SecurePass123!")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Login successful"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john.doe@example.com")
     *                 ),
     *                 @OA\Property(property="token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."),
     *                 @OA\Property(property="token_type", type="string", example="bearer"),
     *                 @OA\Property(property="expires_in", type="integer", example=3600),
     *                 @OA\Property(property="session_id", type="string", example="550e8400-e29b-41d4-a716-446655440000")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Invalid credentials")
     * )
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        try {
            if (!$token = auth('api')->attempt($credentials)) {
                // Log failed login attempt
                $user = User::where('email', $request->email)->first();
                if ($user) {
                    $this->logActivity($user->id, 'login', 'read', 'User', $user->id, "Failed login attempt: {$request->email}", $request, 'failure', 'Invalid password', false, $user->tenant_id);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid email or password',
                ], 401);
            }

            $user = auth('api')->user();

            // Update last login
            $user->update(['last_login_at' => now()]);

            // Create session
            $sessionId = Str::uuid()->toString();
            $session = UserSession::create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'session_id' => $sessionId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'device_type' => $this->detectDeviceType($request->userAgent()),
                'browser' => $this->detectBrowser($request->userAgent()),
                'platform' => $this->detectPlatform($request->userAgent()),
                'location' => $this->detectLocation($request->ip()),
                'started_at' => now(),
                'last_activity_at' => now(),
                'is_active' => true,
            ]);

            // Log successful login
            $this->logActivity($user->id, 'login', 'read', 'User', $user->id, "User logged in: {$user->email}", $request, 'success', null, false, $user->tenant_id);

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => $user->load('tenant'),
                    'token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => auth('api')->factory()->getTTL() * 60,
                    'session_id' => $sessionId,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/auth/me",
     *     summary="Get current user",
     *     operationId="me",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", example="john.doe@example.com"),
     *                 @OA\Property(property="phone", type="string", example="+1234567890"),
     *                 @OA\Property(property="status", type="string", example="active"),
     *                 @OA\Property(property="last_login_at", type="string", format="date-time", example="2023-10-27T10:00:00.000000Z"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2023-10-27T09:00:00.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2023-10-27T10:00:00.000000Z"),
     *                 @OA\Property(property="tenant", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Acme Corp"),
     *                     @OA\Property(property="slug", type="string", example="acme-corp")
     *                 ),
     *                 @OA\Property(property="teams", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="assignments", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="roles", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function me(): JsonResponse
    {
        try {
            $user = auth('api')->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            return response()->json([
                'success' => true,
                'data' => $user->load([
                    'tenant',
                    'teams',
                    'assignments',
                    'roles.permissions',
                ]),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/auth/logout",
     *     summary="Logout user",
     *     operationId="logout",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logout successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Successfully logged out")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $user = auth('api')->user();

            // End all active sessions
            UserSession::where('user_id', $user->id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'ended_at' => now(),
                    'duration_seconds' => \DB::raw('EXTRACT(EPOCH FROM (NOW() - started_at))'),
                ]);

            // Log logout activity
            $this->logActivity($user->id, 'logout', 'update', 'User', $user->id, "User logged out: {$user->email}", $request, 'success', null, false, $user->tenant_id);

            auth('api')->logout();

            return response()->json([
                'success' => true,
                'message' => 'Successfully logged out',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/auth/refresh",
     *     summary="Refresh token",
     *     operationId="refresh",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Token refreshed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Token refreshed successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."),
     *                 @OA\Property(property="token_type", type="string", example="bearer"),
     *                 @OA\Property(property="expires_in", type="integer", example=3600)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function refresh(): JsonResponse
    {
        try {
            $newToken = auth('api')->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed successfully',
                'data' => [
                    'token' => $newToken,
                    'token_type' => 'bearer',
                    'expires_in' => auth('api')->factory()->getTTL() * 60,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token refresh failed',
                'error' => $e->getMessage(),
            ], 401);
        }
    }

    /**
     * @OA\Put(
     *     path="/auth/profile",
     *     summary="Update user profile",
     *     operationId="updateProfile",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="John Smith"),
     *             @OA\Property(property="phone", type="string", example="+1234567890"),
     *             @OA\Property(property="avatar_url", type="string", example="https://example.com/new-avatar.jpg")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profile updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Profile updated successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Smith"),
     *                 @OA\Property(property="email", type="string", example="john.doe@example.com"),
     *                 @OA\Property(property="phone", type="string", example="+1234567890")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'avatar_url' => ['sometimes', 'nullable', 'url', 'max:500'],
        ]);

        try {
            $user = auth('api')->user();

            $user->update($request->only('name', 'avatar_url'));

            // Log profile update
            $this->logActivity($user->id, 'update', 'update', 'User', $user->id, "Profile updated: {$user->email}", $request);

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => $user->fresh()->load('tenant'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Profile update failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/auth/change-password",
     *     summary="Change user password",
     *     operationId="changePassword",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"current_password","new_password","new_password_confirmation"},
     *             @OA\Property(property="current_password", type="string", format="password", example="OldPass123!"),
     *             @OA\Property(property="new_password", type="string", format="password", example="NewSecurePass456!"),
     *             @OA\Property(property="new_password_confirmation", type="string", format="password", example="NewSecurePass456!")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password changed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Password changed successfully")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        try {
            $user = auth('api')->user();

            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect',
                ], 422);
            }

            $user->update([
                'password' => Hash::make($request->new_password),
            ]);

            // Log password change (sensitive)
            $this->logActivity($user->id, 'update', 'update', 'User', $user->id, "Password changed: {$user->email}", $request, 'success', null, true);

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Password change failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Log user activity.
     */
    private function logActivity(
        string $userId,
        string $activityType,
        string $action,
        string $entityType,
        ?string $entityId,
        string $description,
        Request $request,
        string $status = 'success',
        ?string $errorMessage = null,
        bool $isSensitive = false,
        ?string $tenantId = null
    ): void {
        UserActivity::create([
            'tenant_id' => $tenantId ?? tenant('id'),
            'user_id' => $userId,
            'organization_id' => null,
            'activity_type' => $activityType,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => $description,
            'metadata' => [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'device_type' => $this->detectDeviceType($request->userAgent()),
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'status' => $status,
            'error_message' => $errorMessage,
            'is_sensitive' => $isSensitive,
            'requires_audit' => $isSensitive,
        ]);
    }

    /**
     * Detect device type from user agent.
     */
    private function detectDeviceType(?string $userAgent): string
    {
        if (!$userAgent) {
            return 'unknown';
        }

        if (preg_match('/mobile|android|iphone|ipad|phone/i', $userAgent)) {
            return 'mobile';
        }

        if (preg_match('/tablet|ipad/i', $userAgent)) {
            return 'tablet';
        }

        return 'desktop';
    }

    /**
     * Detect browser from user agent.
     */
    private function detectBrowser(?string $userAgent): string
    {
        if (!$userAgent) {
            return 'unknown';
        }

        if (preg_match('/chrome|chromium|crios/i', $userAgent)) {
            return 'Chrome';
        }

        if (preg_match('/firefox|fxios/i', $userAgent)) {
            return 'Firefox';
        }

        if (preg_match('/safari/i', $userAgent) && !preg_match('/chrome/i', $userAgent)) {
            return 'Safari';
        }

        if (preg_match('/edge/i', $userAgent)) {
            return 'Edge';
        }

        if (preg_match('/opera|opr\//i', $userAgent)) {
            return 'Opera';
        }

        return 'Other';
    }

    /**
     * Detect platform from user agent.
     */
    private function detectPlatform(?string $userAgent): string
    {
        if (!$userAgent) {
            return 'unknown';
        }

        if (preg_match('/windows/i', $userAgent)) {
            return 'Windows';
        }

        if (preg_match('/macintosh|mac os x/i', $userAgent)) {
            return 'macOS';
        }

        if (preg_match('/linux/i', $userAgent)) {
            return 'Linux';
        }

        if (preg_match('/android/i', $userAgent)) {
            return 'Android';
        }

        if (preg_match('/iphone|ipad|ipod/i', $userAgent)) {
            return 'iOS';
        }

        return 'Other';
    }

    /**
     * Detect location from IP (simplified - in production use GeoIP service).
     */
    private function detectLocation(?string $ip): ?string
    {
        // In production, integrate with a GeoIP service like MaxMind
        // For now, return null or basic info
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return 'localhost';
        }

        return null; // In production: use GeoIP lookup
    }
}
