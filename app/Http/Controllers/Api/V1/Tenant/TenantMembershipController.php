<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Tenant - Memberships",
 *     description="Tenant user membership management (invite, activate, suspend, remove)"
 * )
 */
class TenantMembershipController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/tenant/memberships",
     *     tags={"Tenant - Memberships"},
     *     summary="List all members of current tenant",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status (active, invited, suspended, left)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"active", "invited", "suspended", "left"})
     *     ),
     *     @OA\Response(response=200, description="List of members"),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
     *     @OA\Response(response=403, ref="#/components/responses/Forbidden")
     * )
     */
    public function index(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'Tenant context not found'], 400);
        }

        $query = TenantMembership::where('tenant_id', $tenantId)
            ->with('user:id,name,email,phone');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $memberships = $query->orderBy('joined_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $memberships,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/memberships/invite",
     *     tags={"Tenant - Memberships"},
     *     summary="Invite a user to join the tenant",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="phone", type="string", example="+1234567890"),
     *             @OA\Property(property="metadata", type="object", example={"department": "Engineering"})
     *         )
     *     ),
     *     @OA\Response(response=201, description="User invited successfully"),
     *     @OA\Response(response=400, description="Validation error or user already member"),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
     *     @OA\Response(response=403, ref="#/components/responses/Forbidden")
     * )
     */
    public function invite(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'Tenant context not found'], 400);
        }

        // Authorization check - requires tenant.memberships.manage permission
        if (!$request->user()->hasTenantPermission('tenant.memberships.manage', $tenantId)) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Check if user exists
            $user = User::where('email', $request->email)->first();

            // If user doesn't exist, create invited user
            if (!$user) {
                $user = User::create([
                    'name' => $request->name ?? explode('@', $request->email)[0],
                    'email' => $request->email,
                    'phone' => $request->phone,
                    'password' => bcrypt(bin2hex(random_bytes(16))), // Random password, user will set on activation
                    'status' => 'invited',
                ]);
            }

            // Check if already a member
            $existingMembership = TenantMembership::where('tenant_id', $tenantId)
                ->where('user_id', $user->id)
                ->first();

            if ($existingMembership) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error' => 'User is already a member of this tenant',
                    'current_status' => $existingMembership->status,
                ], 400);
            }

            // Create membership with invited status
            $membership = TenantMembership::create([
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
                'status' => 'invited',
                'invited_at' => now(),
                'metadata' => $request->metadata ?? [],
            ]);

            // TODO: Send invitation email to user
            // event(new UserInvitedToTenant($user, $tenantId, $membership));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User invited successfully',
                'data' => [
                    'membership' => $membership->load('user:id,name,email,phone'),
                    'user' => $user,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Failed to invite user',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/memberships/{userId}/activate",
     *     tags={"Tenant - Memberships"},
     *     summary="Activate an invited user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="userId",
     *         in="path",
     *         required=true,
     *         description="User ID to activate",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="User activated successfully"),
     *     @OA\Response(response=400, description="User not found or not in invited status"),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
     *     @OA\Response(response=403, ref="#/components/responses/Forbidden")
     * )
     */
    public function activate(Request $request, $userId)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'Tenant context not found'], 400);
        }

        // Authorization check - requires tenant.memberships.manage permission
        if (!$request->user()->hasTenantPermission('tenant.memberships.manage', $tenantId)) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $membership = TenantMembership::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->first();

        if (!$membership) {
            return response()->json([
                'success' => false,
                'error' => 'Membership not found',
            ], 404);
        }

        if ($membership->status !== 'invited') {
            return response()->json([
                'success' => false,
                'error' => 'User is not in invited status',
                'current_status' => $membership->status,
            ], 400);
        }

        $membership->update([
            'status' => 'active',
            'joined_at' => now(),
        ]);

        // Update user status if still invited
        $user = User::find($userId);
        if ($user && $user->status === 'invited') {
            $user->update(['status' => 'active']);
        }

        return response()->json([
            'success' => true,
            'message' => 'User activated successfully',
            'data' => $membership->load('user:id,name,email,phone'),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/memberships/{userId}/suspend",
     *     tags={"Tenant - Memberships"},
     *     summary="Suspend a member",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="userId",
     *         in="path",
     *         required=true,
     *         description="User ID to suspend",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="reason", type="string", example="Violation of terms")
     *         )
     *     ),
     *     @OA\Response(response=200, description="User suspended successfully"),
     *     @OA\Response(response=404, description="Membership not found"),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
     *     @OA\Response(response=403, ref="#/components/responses/Forbidden")
     * )
     */
    public function suspend(Request $request, $userId)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'Tenant context not found'], 400);
        }

        // Authorization check
        if (!$request->user()->hasTenantPermission('tenant.memberships.manage', $tenantId)) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $membership = TenantMembership::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->first();

        if (!$membership) {
            return response()->json([
                'success' => false,
                'error' => 'Membership not found',
            ], 404);
        }

        $metadata = $membership->metadata ?? [];
        $metadata['suspension_reason'] = $request->input('reason', 'No reason provided');
        $metadata['suspended_at'] = now()->toIso8601String();
        $metadata['suspended_by'] = $request->user()->id;

        $membership->update([
            'status' => 'suspended',
            'metadata' => $metadata,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User suspended successfully',
            'data' => $membership->load('user:id,name,email,phone'),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/memberships/{userId}/reactivate",
     *     tags={"Tenant - Memberships"},
     *     summary="Reactivate a suspended member",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="userId",
     *         in="path",
     *         required=true,
     *         description="User ID to reactivate",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="User reactivated successfully"),
     *     @OA\Response(response=404, description="Membership not found"),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
     *     @OA\Response(response=403, ref="#/components/responses/Forbidden")
     * )
     */
    public function reactivate(Request $request, $userId)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'Tenant context not found'], 400);
        }

        // Authorization check
        if (!$request->user()->hasTenantPermission('tenant.memberships.manage', $tenantId)) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $membership = TenantMembership::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->first();

        if (!$membership) {
            return response()->json([
                'success' => false,
                'error' => 'Membership not found',
            ], 404);
        }

        $membership->update([
            'status' => 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User reactivated successfully',
            'data' => $membership->load('user:id,name,email,phone'),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tenant/memberships/{userId}",
     *     tags={"Tenant - Memberships"},
     *     summary="Remove a member from tenant",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="userId",
     *         in="path",
     *         required=true,
     *         description="User ID to remove",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="User removed successfully"),
     *     @OA\Response(response=404, description="Membership not found"),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
     *     @OA\Response(response=403, ref="#/components/responses/Forbidden")
     * )
     */
    public function destroy(Request $request, $userId)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'Tenant context not found'], 400);
        }

        // Authorization check
        if (!$request->user()->hasTenantPermission('tenant.memberships.manage', $tenantId)) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $membership = TenantMembership::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->first();

        if (!$membership) {
            return response()->json([
                'success' => false,
                'error' => 'Membership not found',
            ], 404);
        }

        // Prevent self-removal if user is the only active admin
        // TODO: Add logic to check if user is the last admin

        $membership->update([
            'status' => 'left',
            'left_at' => now(),
        ]);

        // Optionally delete the membership instead of soft-marking
        // $membership->delete();

        return response()->json([
            'success' => true,
            'message' => 'User removed from tenant successfully',
        ]);
    }
}
