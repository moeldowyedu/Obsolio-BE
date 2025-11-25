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
     *             required={"type","fullName","email","password","password_confirmation"},
     *             @OA\Property(property="type", type="string", enum={"personal", "organization"}, example="personal", description="Tenant type"),
     *             @OA\Property(property="fullName", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="SecurePass123!"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="SecurePass123!"),
     *             @OA\Property(property="organizationName", type="string", example="Acme Corporation", description="Required if type=organization"),
     *             @OA\Property(property="organizationDomain", type="string", example="acme.example.com", description="Optional custom domain"),
     *             @OA\Property(property="phone", type="string", example="+1234567890"),
     *             @OA\Property(property="avatar_url", type="string", example="https://example.com/avatar.jpg")
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
     *                     @OA\Property(property="status", type="string", example="active")
     *                 ),
     *                 @OA\Property(property="tenant", type="object",
     *                     @OA\Property(property="id", type="string", example="9d45f8a0-1234-5678-9abc-def012345678"),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="type", type="string", example="personal"),
     *                     @OA\Property(property="slug", type="string", example="john-doe-abc123")
     *                 ),
     *                 @OA\Property(property="token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."),
     *                 @OA\Property(property="token_type", type="string", example="bearer"),
     *                 @OA\Property(property="expires_in", type="integer", example=3600),
     *                 @OA\Property(property="session_id", type="string", example="550e8400-e29b-41d4-a716-446655440000")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            return DB::transaction(function () use ($request) {
                // Step 1: Create Tenant based on type
                $tenantData = $this->prepareTenantData($request);
                $tenant = Tenant::create($tenantData);

                // Step 2: Create User with tenant_id
                $user = User::create([
                    'tenant_id' => $tenant->id,
                    'name' => $request->fullName,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'avatar_url' => $request->avatar_url,
                    'phone' => $request->phone ?? '', // Default to empty string since DB column is NOT NULL
                    'status' => 'active',
                ]);

                // Step 3: Create Membership with owner role
                TenantMembership::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'role' => TenantMembership::ROLE_OWNER,
                    'joined_at' => now(),
                ]);

                // Step 4: Generate JWT token (includes tenant_id and role in claims)
                $token = JWTAuth::fromUser($user);

                // Step 5: Create session
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

                // Step 6: Log registration activity
                $this->logActivity(
                    $user->id,
                    'registration',
                    'create',
                    'User',
                    $user->id,
                    "User registered: {$user->email} ({$request->type} tenant)",
                    $request,
                    'success',
                    null,
                    false,
                    $tenant->id
                );

                // Step 7: Return success response
                return response()->json([
                    'success' => true,
                    'message' => 'Registration successful',
                    'data' => [
                        'user' => [
                            'id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                            'phone' => $user->phone,
                            'status' => $user->status,
                        ],
                        'tenant' => [
                            'id' => $tenant->id,
                            'name' => $tenant->name,
                            'type' => $tenant->type,
                            'slug' => $tenant->slug,
                        ],
                        'token' => $token,
                        'token_type' => 'bearer',
                        'expires_in' => (int) config('jwt.ttl') * 60,
                        'session_id' => $sessionId,
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
     * Prepare tenant data based on registration type.
     */
    private function prepareTenantData(RegisterRequest $request): array
    {
        $type = $request->type;

        if ($type === 'personal') {
            // Personal tenant
            $name = $request->fullName;
            $slug = Str::slug($name . '-' . Str::random(6));

            return [
                'name' => $name,
                'short_name' => Str::limit($name, 20, ''),
                'slug' => $slug,
                'type' => 'personal',
                'status' => 'active',
                'setup_completed' => true,
                'setup_completed_at' => now(),
            ];
        } else {
            // Organization tenant
            $name = $request->organizationName;
            $slug = Str::slug($name . '-' . Str::random(6));

            $data = [
                'name' => $name,
                'short_name' => Str::limit($name, 20, ''),
                'slug' => $slug,
                'type' => 'organization',
                'status' => 'active',
                'setup_completed' => true,
                'setup_completed_at' => now(),
            ];

            // Add domain if provided
            if ($request->organizationDomain) {
                $data['domain'] = $request->organizationDomain;
            }

            return $data;
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
