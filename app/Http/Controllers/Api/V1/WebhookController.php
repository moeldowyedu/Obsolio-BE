<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWebhookRequest;
use App\Http\Requests\UpdateWebhookRequest;
use App\Http\Resources\WebhookResource;
use App\Models\Webhook;
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

        activity()
            ->performedOn($webhook)
            ->causedBy(auth()->user())
            ->log('Webhook created');

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

        return new WebhookResource($webhook);
    }

    /**
     * Update the specified webhook.
     */
    public function update(UpdateWebhookRequest $request, Webhook $webhook): WebhookResource
    {
        $this->authorize('update', $webhook);

        $webhook->update($request->validated());

        activity()
            ->performedOn($webhook)
            ->causedBy(auth()->user())
            ->log('Webhook updated');

        $webhook->load(['createdBy']);

        return new WebhookResource($webhook);
    }

    /**
     * Remove the specified webhook.
     */
    public function destroy(Webhook $webhook): JsonResponse
    {
        $this->authorize('delete', $webhook);

        activity()
            ->performedOn($webhook)
            ->causedBy(auth()->user())
            ->log('Webhook deleted');

        $webhook->delete();

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

            activity()
                ->performedOn($webhook)
                ->causedBy(auth()->user())
                ->withProperties([
                    'status_code' => $response->status(),
                    'response_body' => $response->body(),
                ])
                ->log('Webhook tested');

            return response()->json([
                'message' => 'Webhook test successful',
                'status_code' => $response->status(),
                'response' => $response->body(),
            ], 200);
        } catch (\Exception $e) {
            activity()
                ->performedOn($webhook)
                ->causedBy(auth()->user())
                ->withProperties([
                    'error' => $e->getMessage(),
                ])
                ->log('Webhook test failed');

            return response()->json([
                'message' => 'Webhook test failed',
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
