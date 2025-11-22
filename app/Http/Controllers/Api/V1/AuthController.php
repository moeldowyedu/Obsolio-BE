<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Tenant;
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
     * Register a new user.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            return DB::transaction(function () use ($request) {
                // Create tenant for organization or individual users
                $tenantType = $request->input('tenant_type', 'organization');

                // Always create a tenant for new users
                $organizationName = $request->organization_name
                    ?? ($tenantType === 'organization'
                        ? $request->name . "'s Organization"
                        : $request->name . "'s Workspace");

                $tenant = Tenant::create(['id' => Str::uuid()->toString()]);

                // Set tenant data using the proper method
                $tenant->name = $organizationName;
                $tenant->type = $tenantType;
                $tenant->plan = $request->plan;
                $tenant->save();

                $tenantId = $tenant->id;

                $user = User::create([
                    'tenant_id' => $tenantId,
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'avatar_url' => $request->avatar_url,
                    'phone' => $request->phone,
                    'status' => 'active',
                ]);

                // Auto-login: generate JWT token
                $token = JWTAuth::fromUser($user);

                // Create session
                $sessionId = Str::uuid()->toString();
                $session = UserSession::create([
                    'tenant_id' => $tenantId,
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

                // Log registration activity
                $this->logActivity($user->id, 'login', 'create', 'User', $user->id, "User registered: {$user->email}", $request, 'success', null, false, $tenantId);

                return response()->json([
                    'success' => true,
                    'message' => 'Registration successful',
                    'data' => [
                        'user' => $user->load('tenant'),
                        'token' => $token,
                        'token_type' => 'bearer',
                        'expires_in' => (int) config('jwt.ttl') * 60, // Convert minutes to seconds
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
     * Login user and create token.
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
     * Get the authenticated User.
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
     * Log the user out (Invalidate the token).
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
     * Refresh a token.
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
     * Update user profile.
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
     * Change user password.
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
