<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserActivity;
use App\Models\UserAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = User::where('tenant_id', tenant('id'))
            ->with(['roles', 'teams'])
            ->withCount(['createdAgents', 'createdWorkflows', 'assignments']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by role
        if ($request->has('role')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        // Filter by organization
        if ($request->has('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }

        // Search by name or email
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        // Sorting
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = 'desc';

        if (str_starts_with($sortField, '-')) {
            $sortField = substr($sortField, 1);
            $sortDirection = 'asc';
        }

        $query->orderBy($sortField, $sortDirection);

        $users = $query->paginate($request->get('per_page', 15));

        $this->logActivity('api_call', 'read', 'User', null, 'Listed users');

        return UserResource::collection($users);
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Hash the password if provided
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user = User::create([
            'tenant_id' => tenant('id'),
            ...$data,
        ]);

        // Assign roles if provided
        if ($request->has('roles')) {
            $user->assignRole($request->input('roles'));
        }

        $this->logActivity('create', 'create', 'User', $user->id, "User created: {$user->name}");

        $user->load(['roles', 'teams']);

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified user.
     */
    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);

        $user->load(['roles', 'teams', 'assignments'])
            ->loadCount(['createdAgents', 'createdWorkflows', 'assignedHitlApprovals']);

        $this->logActivity('api_call', 'read', 'User', $user->id, "Viewed user: {$user->name}");

        return new UserResource($user);
    }

    /**
     * Update the specified user.
     */
    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $this->authorize('update', $user);

        $data = $request->validated();

        // Hash the password if provided
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        // Sync roles if provided
        if ($request->has('roles')) {
            $user->syncRoles($request->input('roles'));
        }

        $this->logActivity('update', 'update', 'User', $user->id, "User updated: {$user->name}");

        $user->load(['roles', 'teams']);

        return new UserResource($user);
    }

    /**
     * Remove the specified user.
     */
    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        // Prevent deleting yourself
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account',
            ], 422);
        }

        $name = $user->name;
        $user->delete();

        $this->logActivity('delete', 'delete', 'User', $user->id, "User deleted: {$name}");

        return response()->json(null, 204);
    }

    /**
     * Update user status.
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $this->authorize('update-users');

        $request->validate([
            'status' => 'required|in:active,inactive,suspended',
        ]);

        try {
            $user = User::where('tenant_id', tenant('id'))
                ->where('id', $id)
                ->firstOrFail();

            // Prevent changing your own status
            if ($user->id === auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot change your own status',
                ], 422);
            }

            $oldStatus = $user->status;
            $user->update(['status' => $request->status]);

            $this->logActivity('update', 'update', 'User', $user->id, "User status changed from {$oldStatus} to {$request->status}: {$user->name}");

            return response()->json([
                'success' => true,
                'message' => 'User status updated successfully',
                'data' => new UserResource($user->fresh()->load('roles', 'teams')),
            ]);
        } catch (\Exception $e) {
            $this->logActivity('update', 'update', 'User', $id, 'Failed to update user status', 'failure', $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update user status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Assign user to entity.
     */
    public function assign(Request $request, string $id): JsonResponse
    {
        $this->authorize('update-users');

        $request->validate([
            'entity_type' => 'required|in:Project,Team,Department,Branch',
            'entity_id' => 'required|uuid',
            'role' => 'nullable|string|max:50',
        ]);

        try {
            $user = User::where('tenant_id', tenant('id'))
                ->where('id', $id)
                ->firstOrFail();

            $assignment = UserAssignment::create([
                'tenant_id' => tenant('id'),
                'user_id' => $user->id,
                'entity_type' => $request->entity_type,
                'entity_id' => $request->entity_id,
                'role' => $request->role,
                'assigned_by_user_id' => auth()->id(),
            ]);

            $this->logActivity('create', 'create', 'UserAssignment', $assignment->id, "User assigned to {$request->entity_type}: {$user->name}");

            return response()->json([
                'success' => true,
                'message' => 'User assigned successfully',
                'data' => $assignment,
            ], 201);
        } catch (\Exception $e) {
            $this->logActivity('create', 'create', 'UserAssignment', null, 'Failed to assign user', 'failure', $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user assignments.
     */
    public function assignments(Request $request, string $id): JsonResponse
    {
        $this->authorize('view-users');

        try {
            $user = User::where('tenant_id', tenant('id'))
                ->where('id', $id)
                ->firstOrFail();

            $assignments = UserAssignment::where('tenant_id', tenant('id'))
                ->where('user_id', $user->id)
                ->with(['assignedBy'])
                ->paginate($request->get('per_page', 15));

            $this->logActivity('api_call', 'read', 'UserAssignment', null, "Viewed assignments for user: {$user->name}");

            return response()->json([
                'success' => true,
                'data' => $assignments,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user assignments',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Log user activity.
     */
    private function logActivity(
        string $activityType,
        string $action,
        string $entityType,
        ?string $entityId,
        string $description,
        string $status = 'success',
        ?string $errorMessage = null
    ): void {
        UserActivity::create([
            'tenant_id' => tenant('id'),
            'user_id' => request()->user()->id,
            'organization_id' => request()->user()->organization_id ?? null,
            'activity_type' => $activityType,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => $description,
            'metadata' => [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'request_id' => request()->header('X-Request-ID'),
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'status' => $status,
            'error_message' => $errorMessage,
            'is_sensitive' => false,
            'requires_audit' => false,
        ]);
    }
}
