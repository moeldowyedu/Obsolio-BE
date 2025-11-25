<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Integrations\StoreConnectedAppRequest;
use App\Http\Requests\Integrations\UpdateConnectedAppRequest;
use App\Models\ConnectedApp;
use App\Models\ConnectedAppLog;
use App\Models\UserActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ConnectedAppController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    /**
     * @OA\Get(
     *     path="/connected-apps",
     *     summary="List connected apps",
     *     operationId="getConnectedApps",
     *     tags={"Connected Apps"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('view-connected-apps');

        $query = ConnectedApp::query()
            ->where('tenant_id', tenant('id'))
            ->where('user_id', $request->user()->id)
            ->with(['user:id,name,email', 'organization:id,name'])
            ->latest();

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('app_type')) {
            $query->where('app_type', $request->app_type);
        }

        if ($request->has('provider')) {
            $query->where('provider', $request->provider);
        }

        if ($request->has('search')) {
            $query->where('app_name', 'like', '%' . $request->search . '%');
        }

        $connectedApps = $query->paginate($request->get('per_page', 15));

        $this->logActivity('api_call', 'read', 'ConnectedApp', null, 'Listed connected apps');

        return response()->json([
            'success' => true,
            'data' => $connectedApps,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    /**
     * @OA\Post(
     *     path="/connected-apps",
     *     summary="Connect new app",
     *     operationId="createConnectedApp",
     *     tags={"Connected Apps"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Connected app created successfully",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(StoreConnectedAppRequest $request): JsonResponse
    {
        try {
            $connectedApp = ConnectedApp::create([
                'tenant_id' => tenant('id'),
                'user_id' => $request->user()->id,
                'organization_id' => $request->organization_id,
                'app_name' => $request->app_name,
                'app_type' => $request->app_type,
                'provider' => $request->provider,
                'description' => $request->description,
                'client_id' => $request->client_id,
                'client_secret' => $request->client_secret,
                'credentials' => $request->credentials,
                'scopes' => $request->scopes ?? [],
                'settings' => $request->settings ?? [],
                'status' => 'active',
                'callback_url' => $request->callback_url,
                'token_expires_at' => $request->token_expires_at,
                'total_requests' => 0,
                'failed_requests' => 0,
            ]);

            $this->logActivity('create', 'create', 'ConnectedApp', $connectedApp->id, "Connected app created: {$connectedApp->app_name}");

            return response()->json([
                'success' => true,
                'message' => 'Connected app created successfully',
                'data' => $connectedApp->load(['user:id,name,email', 'organization:id,name']),
            ], 201);
        } catch (\Exception $e) {
            $this->logActivity('create', 'create', 'ConnectedApp', null, 'Failed to create connected app', 'failure', $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create connected app',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    /**
     * @OA\Get(
     *     path="/connected-apps/{id}",
     *     summary="Get app details",
     *     operationId="getConnectedApp",
     *     tags={"Connected Apps"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Connected App ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=404, description="App not found")
     * )
     */
    public function show(string $id): JsonResponse
    {
        $this->authorize('view-connected-apps');

        $connectedApp = ConnectedApp::where('tenant_id', tenant('id'))
            ->where('id', $id)
            ->where('user_id', request()->user()->id)
            ->with(['user:id,name,email', 'organization:id,name'])
            ->firstOrFail();

        $this->logActivity('api_call', 'read', 'ConnectedApp', $connectedApp->id, "Viewed connected app: {$connectedApp->app_name}");

        return response()->json([
            'success' => true,
            'data' => $connectedApp,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    /**
     * @OA\Put(
     *     path="/connected-apps/{id}",
     *     summary="Update app",
     *     operationId="updateConnectedApp",
     *     tags={"Connected Apps"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Connected App ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Connected app updated successfully",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(UpdateConnectedAppRequest $request, string $id): JsonResponse
    {
        try {
            $connectedApp = ConnectedApp::where('tenant_id', tenant('id'))
                ->where('id', $id)
                ->where('user_id', $request->user()->id)
                ->firstOrFail();

            $connectedApp->update($request->validated());

            $this->logActivity('update', 'update', 'ConnectedApp', $connectedApp->id, "Updated connected app: {$connectedApp->app_name}");

            return response()->json([
                'success' => true,
                'message' => 'Connected app updated successfully',
                'data' => $connectedApp->fresh()->load(['user:id,name,email', 'organization:id,name']),
            ]);
        } catch (\Exception $e) {
            $this->logActivity('update', 'update', 'ConnectedApp', $id, 'Failed to update connected app', 'failure', $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update connected app',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    /**
     * @OA\Delete(
     *     path="/connected-apps/{id}",
     *     summary="Disconnect app",
     *     operationId="deleteConnectedApp",
     *     tags={"Connected Apps"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Connected App ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Connected app deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=404, description="App not found")
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        $this->authorize('delete-connected-apps');

        try {
            $connectedApp = ConnectedApp::where('tenant_id', tenant('id'))
                ->where('id', $id)
                ->where('user_id', request()->user()->id)
                ->firstOrFail();

            $name = $connectedApp->app_name;
            $connectedApp->delete();

            $this->logActivity('delete', 'delete', 'ConnectedApp', $id, "Deleted connected app: {$name}");

            return response()->json([
                'success' => true,
                'message' => 'Connected app deleted successfully',
            ]);
        } catch (\Exception $e) {
            $this->logActivity('delete', 'delete', 'ConnectedApp', $id, 'Failed to delete connected app', 'failure', $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete connected app',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync data with connected app.
     */
    /**
     * @OA\Post(
     *     path="/connected-apps/{id}/sync",
     *     summary="Sync app data",
     *     operationId="syncConnectedApp",
     *     tags={"Connected Apps"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Connected App ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data synchronized successfully",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function sync(string $id): JsonResponse
    {
        $this->authorize('update-connected-apps');

        try {
            $connectedApp = ConnectedApp::where('tenant_id', tenant('id'))
                ->where('id', $id)
                ->where('user_id', request()->user()->id)
                ->firstOrFail();

            if (!$connectedApp->isActive()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot sync. App is not active or token expired',
                ], 400);
            }

            // Simulate sync operation (implement actual sync logic based on provider)
            $connectedApp->update([
                'last_synced_at' => now(),
            ]);

            $this->createAppLog($connectedApp->id, 'sync', 'success', null, null, 'Data synced successfully');

            $this->logActivity('api_call', 'execute', 'ConnectedApp', $connectedApp->id, "Synced data with: {$connectedApp->app_name}");

            return response()->json([
                'success' => true,
                'message' => 'Data synchronized successfully',
                'data' => $connectedApp->fresh(),
            ]);
        } catch (\Exception $e) {
            if (isset($connectedApp)) {
                $this->createAppLog($connectedApp->id, 'sync', 'failure', null, null, $e->getMessage());
            }

            $this->logActivity('api_call', 'execute', 'ConnectedApp', $id, 'Failed to sync data', 'failure', $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to synchronize data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test connection to connected app.
     */
    /**
     * @OA\Post(
     *     path="/connected-apps/{id}/test",
     *     summary="Test app connection",
     *     operationId="testConnectedApp",
     *     tags={"Connected Apps"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Connected App ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Connection test successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function testConnection(string $id): JsonResponse
    {
        $this->authorize('update-connected-apps');

        try {
            $connectedApp = ConnectedApp::where('tenant_id', tenant('id'))
                ->where('id', $id)
                ->where('user_id', request()->user()->id)
                ->firstOrFail();

            // Simulate connection test (implement actual test based on provider)
            $testResult = $this->performConnectionTest($connectedApp);

            if ($testResult['success']) {
                $connectedApp->update(['last_used_at' => now()]);
                $this->createAppLog($connectedApp->id, 'test_connection', 'success', null, null, 'Connection test successful');

                $this->logActivity('api_call', 'execute', 'ConnectedApp', $connectedApp->id, "Tested connection: {$connectedApp->app_name}");

                return response()->json([
                    'success' => true,
                    'message' => 'Connection test successful',
                    'data' => $testResult['data'] ?? null,
                ]);
            }

            $this->createAppLog($connectedApp->id, 'test_connection', 'failure', null, null, $testResult['message'] ?? 'Connection failed');

            return response()->json([
                'success' => false,
                'message' => 'Connection test failed',
                'error' => $testResult['message'] ?? 'Unknown error',
            ], 400);
        } catch (\Exception $e) {
            if (isset($connectedApp)) {
                $this->createAppLog($connectedApp->id, 'test_connection', 'failure', null, null, $e->getMessage());
            }

            $this->logActivity('api_call', 'execute', 'ConnectedApp', $id, 'Connection test failed', 'failure', $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Connection test failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Refresh OAuth token.
     */
    /**
     * @OA\Post(
     *     path="/connected-apps/{id}/refresh-token",
     *     summary="Refresh app token",
     *     operationId="refreshConnectedAppToken",
     *     tags={"Connected Apps"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Connected App ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Token refreshed successfully",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function refreshToken(string $id): JsonResponse
    {
        $this->authorize('update-connected-apps');

        try {
            $connectedApp = ConnectedApp::where('tenant_id', tenant('id'))
                ->where('id', $id)
                ->where('user_id', request()->user()->id)
                ->firstOrFail();

            if ($connectedApp->app_type !== 'oauth') {
                return response()->json([
                    'success' => false,
                    'message' => 'Token refresh only available for OAuth apps',
                ], 400);
            }

            // Simulate token refresh (implement actual refresh logic based on provider)
            $connectedApp->update([
                'token_expires_at' => now()->addDays(30),
                'last_synced_at' => now(),
            ]);

            $this->createAppLog($connectedApp->id, 'refresh_token', 'success', null, null, 'Token refreshed successfully');

            $this->logActivity('update', 'update', 'ConnectedApp', $connectedApp->id, "Refreshed token: {$connectedApp->app_name}", 'success', null, true);

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed successfully',
                'data' => $connectedApp->fresh(),
            ]);
        } catch (\Exception $e) {
            if (isset($connectedApp)) {
                $this->createAppLog($connectedApp->id, 'refresh_token', 'failure', null, null, $e->getMessage());
                $connectedApp->increment('failed_requests');
            }

            $this->logActivity('update', 'update', 'ConnectedApp', $id, 'Failed to refresh token', 'failure', $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh token',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get app logs.
     */
    /**
     * @OA\Get(
     *     path="/connected-apps/{id}/logs",
     *     summary="Get app logs",
     *     operationId="getConnectedAppLogs",
     *     tags={"Connected Apps"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Connected App ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function logs(Request $request, string $id): JsonResponse
    {
        $this->authorize('view-connected-apps');

        $connectedApp = ConnectedApp::where('tenant_id', tenant('id'))
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $query = ConnectedAppLog::where('connected_app_id', $id)
            ->latest('created_at');

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }

        $logs = $query->paginate($request->get('per_page', 20));

        $this->logActivity('api_call', 'read', 'ConnectedAppLog', null, "Viewed logs for: {$connectedApp->app_name}");

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Revoke app access.
     */
    /**
     * @OA\Post(
     *     path="/connected-apps/{id}/revoke",
     *     summary="Revoke app access",
     *     operationId="revokeConnectedApp",
     *     tags={"Connected Apps"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Connected App ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="App access revoked successfully",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function revoke(string $id): JsonResponse
    {
        $this->authorize('revoke-connected-apps');

        try {
            $connectedApp = ConnectedApp::where('tenant_id', tenant('id'))
                ->where('id', $id)
                ->where('user_id', request()->user()->id)
                ->firstOrFail();

            $connectedApp->update([
                'status' => 'revoked',
                'credentials' => null,
                'client_secret' => null,
            ]);

            $this->createAppLog($connectedApp->id, 'revoke', 'success', null, null, 'App access revoked');

            $this->logActivity('update', 'update', 'ConnectedApp', $connectedApp->id, "Revoked access: {$connectedApp->app_name}", 'success', null, true);

            return response()->json([
                'success' => true,
                'message' => 'App access revoked successfully',
                'data' => $connectedApp->fresh(),
            ]);
        } catch (\Exception $e) {
            $this->logActivity('update', 'update', 'ConnectedApp', $id, 'Failed to revoke access', 'failure', $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to revoke app access',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Perform connection test based on provider.
     */
    private function performConnectionTest(ConnectedApp $app): array
    {
        try {
            // Simulate connection test - In production, implement actual API calls
            // based on provider (GitHub, GitLab, Slack, etc.)

            return [
                'success' => true,
                'message' => 'Connection successful',
                'data' => [
                    'status' => 'connected',
                    'tested_at' => now(),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create connected app log entry.
     */
    private function createAppLog(
        string $connectedAppId,
        string $action,
        string $status,
        ?array $requestData = null,
        ?array $responseData = null,
        ?string $errorMessage = null
    ): void {
        ConnectedAppLog::create([
            'connected_app_id' => $connectedAppId,
            'action' => $action,
            'status' => $status,
            'request_data' => $requestData,
            'response_data' => $responseData,
            'error_message' => $errorMessage,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
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
