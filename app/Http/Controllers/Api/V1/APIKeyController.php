<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Integrations\StoreAPIKeyRequest;
use App\Http\Requests\Integrations\UpdateAPIKeyRequest;
use App\Models\APIKey;
use App\Models\UserActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class APIKeyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('view-api-keys');

        $query = APIKey::query()
            ->where('tenant_id', tenant('id'))
            ->where('user_id', $request->user()->id)
            ->with('createdBy:id,name,email')
            ->latest();

        // Apply filters
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $apiKeys = $query->paginate($request->get('per_page', 15));

        // Log activity
        $this->logActivity('api_call', 'read', 'APIKey', null, 'Listed API keys');

        return response()->json([
            'success' => true,
            'data' => $apiKeys,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAPIKeyRequest $request): JsonResponse
    {
        try {
            // Generate unique API key
            $key = 'aasim_' . Str::random(40);
            $prefix = 'aasim_' . Str::random(8);

            $apiKey = APIKey::create([
                'tenant_id' => tenant('id'),
                'user_id' => $request->user()->id,
                'name' => $request->name,
                'key' => hash('sha256', $key),
                'key_prefix' => $prefix,
                'scopes' => $request->scopes ?? ['read'],
                'expires_at' => $request->expires_at,
                'is_active' => true,
            ]);

            // Log activity
            $this->logActivity('create', 'create', 'APIKey', $apiKey->id, "Created API key: {$apiKey->name}");

            return response()->json([
                'success' => true,
                'message' => 'API key created successfully',
                'data' => [
                    'api_key' => $apiKey->load('createdBy:id,name,email'),
                    'plain_key' => $key, // Only shown once
                ],
            ], 201);
        } catch (\Exception $e) {
            $this->logActivity('create', 'create', 'APIKey', null, 'Failed to create API key', 'failure', $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create API key',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $this->authorize('view-api-keys');

        $apiKey = APIKey::where('tenant_id', tenant('id'))
            ->where('id', $id)
            ->where('user_id', request()->user()->id)
            ->with('createdBy:id,name,email')
            ->firstOrFail();

        $this->logActivity('api_call', 'read', 'APIKey', $apiKey->id, "Viewed API key: {$apiKey->name}");

        return response()->json([
            'success' => true,
            'data' => $apiKey,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAPIKeyRequest $request, string $id): JsonResponse
    {
        try {
            $apiKey = APIKey::where('tenant_id', tenant('id'))
                ->where('id', $id)
                ->where('user_id', $request->user()->id)
                ->firstOrFail();

            $apiKey->update($request->validated());

            $this->logActivity('update', 'update', 'APIKey', $apiKey->id, "Updated API key: {$apiKey->name}");

            return response()->json([
                'success' => true,
                'message' => 'API key updated successfully',
                'data' => $apiKey->fresh()->load('createdBy:id,name,email'),
            ]);
        } catch (\Exception $e) {
            $this->logActivity('update', 'update', 'APIKey', $id, 'Failed to update API key', 'failure', $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update API key',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $this->authorize('delete-api-keys');

        try {
            $apiKey = APIKey::where('tenant_id', tenant('id'))
                ->where('id', $id)
                ->where('user_id', request()->user()->id)
                ->firstOrFail();

            $name = $apiKey->name;
            $apiKey->delete();

            $this->logActivity('delete', 'delete', 'APIKey', $id, "Deleted API key: {$name}");

            return response()->json([
                'success' => true,
                'message' => 'API key deleted successfully',
            ]);
        } catch (\Exception $e) {
            $this->logActivity('delete', 'delete', 'APIKey', $id, 'Failed to delete API key', 'failure', $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete API key',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Regenerate API key.
     */
    public function regenerate(string $id): JsonResponse
    {
        $this->authorize('regenerate-api-keys');

        try {
            $apiKey = APIKey::where('tenant_id', tenant('id'))
                ->where('id', $id)
                ->where('user_id', request()->user()->id)
                ->firstOrFail();

            // Generate new key
            $newKey = 'aasim_' . Str::random(40);
            $newPrefix = 'aasim_' . Str::random(8);

            $apiKey->update([
                'key' => hash('sha256', $newKey),
                'key_prefix' => $newPrefix,
            ]);

            $this->logActivity('update', 'update', 'APIKey', $apiKey->id, "Regenerated API key: {$apiKey->name}", 'success', null, true);

            return response()->json([
                'success' => true,
                'message' => 'API key regenerated successfully',
                'data' => [
                    'api_key' => $apiKey->fresh()->load('createdBy:id,name,email'),
                    'plain_key' => $newKey, // Only shown once
                ],
            ]);
        } catch (\Exception $e) {
            $this->logActivity('update', 'update', 'APIKey', $id, 'Failed to regenerate API key', 'failure', $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to regenerate API key',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle API key active status.
     */
    public function toggle(string $id): JsonResponse
    {
        $this->authorize('update-api-keys');

        try {
            $apiKey = APIKey::where('tenant_id', tenant('id'))
                ->where('id', $id)
                ->where('user_id', request()->user()->id)
                ->firstOrFail();

            $apiKey->update([
                'is_active' => !$apiKey->is_active,
            ]);

            $status = $apiKey->is_active ? 'activated' : 'deactivated';
            $this->logActivity('update', 'update', 'APIKey', $apiKey->id, "API key {$status}: {$apiKey->name}");

            return response()->json([
                'success' => true,
                'message' => "API key {$status} successfully",
                'data' => $apiKey->fresh()->load('createdBy:id,name,email'),
            ]);
        } catch (\Exception $e) {
            $this->logActivity('update', 'update', 'APIKey', $id, 'Failed to toggle API key', 'failure', $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle API key status',
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
        ?string $errorMessage = null,
        bool $isSensitive = false
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
            'is_sensitive' => $isSensitive,
            'requires_audit' => $isSensitive,
        ]);
    }
}
