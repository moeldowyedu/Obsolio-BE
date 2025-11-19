<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(): AnonymousResourceCollection
    {
        $users = User::where('tenant_id', tenant('id'))
            ->with(['roles', 'teams'])
            ->withCount(['createdAgents', 'createdWorkflows', 'assignments'])
            ->paginate(request('per_page', 15));

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

        activity()
            ->performedOn($user)
            ->causedBy(auth()->user())
            ->log('User created');

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

        activity()
            ->performedOn($user)
            ->causedBy(auth()->user())
            ->log('User updated');

        $user->load(['roles', 'teams']);

        return new UserResource($user);
    }

    /**
     * Remove the specified user.
     */
    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        activity()
            ->performedOn($user)
            ->causedBy(auth()->user())
            ->log('User deleted');

        $user->delete();

        return response()->json(null, 204);
    }

    /**
     * Assign roles to the specified user.
     */
    public function assignRoles(Request $request, User $user): UserResource
    {
        $this->authorize('update', $user);

        $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'string|exists:roles,name',
        ]);

        $user->syncRoles($request->input('roles'));

        activity()
            ->performedOn($user)
            ->causedBy(auth()->user())
            ->log('User roles updated');

        $user->load(['roles', 'teams']);

        return new UserResource($user);
    }
}
