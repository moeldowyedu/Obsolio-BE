<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserActivity;
use App\Models\UserSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantSetupController extends Controller
{
    /**
     * Set up organization tenant.
     */
    public function setupOrganization(Request $request): JsonResponse
    {
        $request->validate([
            'organization_name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/', 'unique:tenants,slug'],
            'plan_id' => ['nullable', 'string', 'max:100'],
            'billing_cycle' => ['nullable', 'string', 'in:monthly,yearly'],
            'billing_info' => ['nullable', 'array'],
        ]);

        try {
            $user = auth('api')->user();

            // Check if user already has a tenant
            if ($user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User already has a tenant set up',
                ], 400);
            }

            return DB::transaction(function () use ($request, $user) {
                // Create tenant with UUID
                $tenant = Tenant::create([
                    'id' => Str::uuid()->toString(),
                ]);

                // Generate slug if not provided
                $slug = $request->slug ?? Str::slug($request->organization_name);

                // Ensure slug uniqueness
                $originalSlug = $slug;
                $counter = 1;
                while (Tenant::where('slug', $slug)->exists()) {
                    $slug = $originalSlug . '-' . $counter;
                    $counter++;
                }

                // Set tenant data
                $tenant->name = $request->organization_name;
                $tenant->organization_name = $request->organization_name;
                $tenant->slug = $slug;
                $tenant->type = 'organization';
                $tenant->plan_id = $request->plan_id;
                $tenant->billing_cycle = $request->billing_cycle;
                $tenant->setup_completed = true;
                $tenant->setup_completed_at = now();

                // Store billing info in data column if provided
                if ($request->billing_info) {
                    $tenant->data = array_merge($tenant->data ?? [], [
                        'billing_info' => $request->billing_info,
                    ]);
                }

                $tenant->save();

                // Update user with tenant_id
                $user->update(['tenant_id' => $tenant->id]);

                // Update active sessions with tenant_id
                UserSession::where('user_id', $user->id)
                    ->where('is_active', true)
                    ->update(['tenant_id' => $tenant->id]);

                // Update user activities with tenant_id
                UserActivity::where('user_id', $user->id)
                    ->whereNull('tenant_id')
                    ->update(['tenant_id' => $tenant->id]);

                // Log tenant setup activity
                $this->logActivity(
                    $user->id,
                    'setup',
                    'create',
                    'Tenant',
                    $tenant->id,
                    "Organization tenant created: {$tenant->name}",
                    $request,
                    'success',
                    null,
                    false,
                    $tenant->id
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Organization tenant set up successfully',
                    'data' => [
                        'tenant' => $tenant,
                        'user' => $user->fresh()->load('tenant'),
                    ],
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant setup failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Set up personal tenant (minimal setup).
     */
    public function setupPersonal(Request $request): JsonResponse
    {
        $request->validate([
            'workspace_name' => ['nullable', 'string', 'max:255'],
            'plan_id' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $user = auth('api')->user();

            // Check if user already has a tenant
            if ($user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User already has a tenant set up',
                ], 400);
            }

            return DB::transaction(function () use ($request, $user) {
                // Create tenant with UUID
                $tenant = Tenant::create([
                    'id' => Str::uuid()->toString(),
                ]);

                // Use provided workspace name or generate from user's name
                $workspaceName = $request->workspace_name ?? $user->name . "'s Workspace";

                // Generate slug from workspace name
                $slug = Str::slug($workspaceName);

                // Ensure slug uniqueness
                $originalSlug = $slug;
                $counter = 1;
                while (Tenant::where('slug', $slug)->exists()) {
                    $slug = $originalSlug . '-' . $counter;
                    $counter++;
                }

                // Set tenant data for personal workspace
                $tenant->name = $workspaceName;
                $tenant->slug = $slug;
                $tenant->type = 'individual';
                $tenant->plan_id = $request->plan_id ?? 'free'; // Default to free plan for personal
                $tenant->setup_completed = true;
                $tenant->setup_completed_at = now();
                $tenant->save();

                // Update user with tenant_id
                $user->update(['tenant_id' => $tenant->id]);

                // Update active sessions with tenant_id
                UserSession::where('user_id', $user->id)
                    ->where('is_active', true)
                    ->update(['tenant_id' => $tenant->id]);

                // Update user activities with tenant_id
                UserActivity::where('user_id', $user->id)
                    ->whereNull('tenant_id')
                    ->update(['tenant_id' => $tenant->id]);

                // Log tenant setup activity
                $this->logActivity(
                    $user->id,
                    'setup',
                    'create',
                    'Tenant',
                    $tenant->id,
                    "Personal tenant created: {$tenant->name}",
                    $request,
                    'success',
                    null,
                    false,
                    $tenant->id
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Personal workspace set up successfully',
                    'data' => [
                        'tenant' => $tenant,
                        'user' => $user->fresh()->load('tenant'),
                    ],
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant setup failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check tenant setup status.
     */
    public function checkSetupStatus(): JsonResponse
    {
        try {
            $user = auth('api')->user();

            $requiresSetup = is_null($user->tenant_id);

            $data = [
                'requires_setup' => $requiresSetup,
                'user' => $user,
            ];

            if (!$requiresSetup) {
                $data['tenant'] = $user->tenant;
            }

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check setup status',
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
            'tenant_id' => $tenantId,
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
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'status' => $status,
            'error_message' => $errorMessage,
            'is_sensitive' => $isSensitive,
            'requires_audit' => $isSensitive,
        ]);
    }
}
