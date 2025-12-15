<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Integrations\StoreWebhookRequest;
use App\Http\Requests\Integrations\UpdateWebhookRequest;
use App\Http\Resources\WebhookResource;
use App\Models\Webhook;
use App\Models\UserActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    /**
     * Display a listing of webhooks.
     */
    public function index(): AnonymousResourceCollection
    {
        $webhooks = Webhook::where('tenant_id', tenant('id'))
            ->with(['createdBy'])
            ->paginate(request('per_page', 15));

        $this->logActivity('api_call', 'read', 'Webhook', null, 'Listed webhooks');

        return WebhookResource::collection($webhooks);
    }

    /**
     * Store a newly created webhook.
     */
    public function store(StoreWebhookRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Generate a secret for the webhook if not provided
        if (!isset($data['secret'])) {
            $data['secret'] = Str::random(64);
        }

        $webhook = Webhook::create([
            'tenant_id' => tenant('id'),
            'created_by_user_id' => auth()->id(),
            ...$data,
        ]);

        $this->logActivity('create', 'create', 'Webhook', $webhook->id, "Webhook created: {$webhook->name}");

        $webhook->load(['createdBy']);

        return (new WebhookResource($webhook))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified webhook.
     */
    public function show(Webhook $webhook): WebhookResource
    {
        $this->authorize('view', $webhook);

        $webhook->load(['createdBy']);

        $this->logActivity('api_call', 'read', 'Webhook', $webhook->id, "Viewed webhook: {$webhook->name}");

        return new WebhookResource($webhook);
    }

    /**
     * Update the specified webhook.
     */
    public function update(UpdateWebhookRequest $request, Webhook $webhook): WebhookResource
    {
        $this->authorize('update', $webhook);

        $webhook->update($request->validated());

        $this->logActivity('update', 'update', 'Webhook', $webhook->id, "Webhook updated: {$webhook->name}");

        $webhook->load(['createdBy']);

        return new WebhookResource($webhook);
    }

    /**
     * Remove the specified webhook.
     */
    public function destroy(Webhook $webhook): JsonResponse
    {
        $this->authorize('delete', $webhook);

        $name = $webhook->name;
        $webhook->delete();

        $this->logActivity('delete', 'delete', 'Webhook', $webhook->id, "Webhook deleted: {$name}");

        return response()->json(null, 204);
    }

    /**
     * Test the specified webhook by sending a test payload.
     */
    public function test(Webhook $webhook): JsonResponse
    {
        $this->authorize('update', $webhook);

        // Prepare test payload
        $testPayload = [
            'event' => 'webhook.test',
            'timestamp' => now()->toIso8601String(),
            'tenant_id' => tenant('id'),
            'data' => [
                'message' => 'This is a test webhook from Aasim AI',
                'webhook_id' => $webhook->id,
            ],
        ];

        try {
            // Send test webhook
            $response = Http::withHeaders([
                'X-Webhook-Secret' => $webhook->secret,
                'Content-Type' => 'application/json',
            ])->timeout(10)->post($webhook->url, $testPayload);

            // Update last triggered timestamp
            $webhook->update([
                'last_triggered_at' => now(),
            ]);

            $this->logActivity('api_call', 'execute', 'Webhook', $webhook->id, "Webhook test successful: {$webhook->name}");

            return response()->json([
                'success' => true,
                'message' => 'Webhook test successful',
                'status_code' => $response->status(),
                'response' => $response->body(),
            ], 200);
        } catch (\Exception $e) {
            $this->logActivity('api_call', 'execute', 'Webhook', $webhook->id, "Webhook test failed: {$webhook->name}", 'failure', $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Webhook test failed',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Toggle webhook active status.
     */
    public function toggle(string $id): JsonResponse
    {
        $this->authorize('update-webhooks');

        try {
            $webhook = Webhook::where('tenant_id', tenant('id'))
                ->where('id', $id)
                ->firstOrFail();

            $webhook->update([
                'is_active' => !$webhook->is_active,
            ]);

            $status = $webhook->is_active ? 'activated' : 'deactivated';
            $this->logActivity('update', 'update', 'Webhook', $webhook->id, "Webhook {$status}: {$webhook->name}");

            return response()->json([
                'success' => true,
                'message' => "Webhook {$status} successfully",
                'data' => new WebhookResource($webhook->fresh()->load('createdBy')),
            ]);
        } catch (\Exception $e) {
            $this->logActivity('update', 'update', 'Webhook', $id, 'Failed to toggle webhook', 'failure', $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle webhook status',
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
