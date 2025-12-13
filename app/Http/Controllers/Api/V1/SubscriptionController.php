<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubscriptionRequest;
use App\Http\Requests\UpdateSubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubscriptionController extends Controller
{
    /**
     * Display a listing of subscriptions.
     */
    public function index(): AnonymousResourceCollection
    {
        $subscriptions = Subscription::where('tenant_id', tenant('id'))
            ->with(['tenant'])
            ->paginate(request('per_page', 15));

        return SubscriptionResource::collection($subscriptions);
    }

    /**
     * Get the current active subscription.
     */
    public function current(): JsonResponse
    {
        /** @var \App\Models\User */
        $user = auth()->user();

        if (!$user->tenant_id) {
            return response()->json([
                'data' => null,
                'message' => 'User does not belong to any tenant.',
            ], 404);
        }

        $tenant = \App\Models\Tenant::find($user->tenant_id);

        if (!$tenant) {
            return response()->json([
                'data' => null,
                'message' => 'Tenant not found for this user.',
            ], 404);
        }

        $subscription = $tenant->activeSubscription()
            ->with('plan')
            ->first();

        if (!$subscription) {
            return response()->json([
                'data' => null,
                'message' => 'No active subscription found.',
            ]);
        }

        return (new SubscriptionResource($subscription))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Store a newly created subscription.
     */
    public function store(StoreSubscriptionRequest $request): JsonResponse
    {
        $subscription = Subscription::create([
            'tenant_id' => tenant('id'),
            ...$request->validated(),
        ]);

        activity()
            ->performedOn($subscription)
            ->causedBy(auth()->user())
            ->log('Subscription created');

        $subscription->load(['tenant']);

        return (new SubscriptionResource($subscription))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified subscription.
     */
    public function show(Subscription $subscription): SubscriptionResource
    {
        $this->authorize('view', $subscription);

        $subscription->load(['tenant']);

        return new SubscriptionResource($subscription);
    }

    /**
     * Update the specified subscription.
     */
    public function update(UpdateSubscriptionRequest $request, Subscription $subscription): SubscriptionResource
    {
        $this->authorize('update', $subscription);

        $subscription->update($request->validated());

        activity()
            ->performedOn($subscription)
            ->causedBy(auth()->user())
            ->log('Subscription updated');

        $subscription->load(['tenant']);

        return new SubscriptionResource($subscription);
    }

    /**
     * Remove the specified subscription.
     */
    public function destroy(Subscription $subscription): JsonResponse
    {
        $this->authorize('delete', $subscription);

        activity()
            ->performedOn($subscription)
            ->causedBy(auth()->user())
            ->log('Subscription deleted');

        $subscription->delete();

        return response()->json(null, 204);
    }

    /**
     * Cancel the specified subscription.
     */
    public function cancel(Subscription $subscription): JsonResponse
    {
        $this->authorize('update', $subscription);

        // Check if subscription is already cancelled
        if ($subscription->isCancelled()) {
            return response()->json([
                'message' => 'Subscription is already cancelled.',
            ], 422);
        }

        // Update subscription status
        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancel_at' => $subscription->current_period_end, // Will be cancelled at end of billing period
        ]);

        activity()
            ->performedOn($subscription)
            ->causedBy(auth()->user())
            ->log('Subscription cancelled');

        // TODO: Call Stripe API to cancel subscription
        // if ($subscription->stripe_subscription_id) {
        //     \Stripe\Subscription::update($subscription->stripe_subscription_id, [
        //         'cancel_at_period_end' => true,
        //     ]);
        // }

        $subscription->load(['tenant']);

        return (new SubscriptionResource($subscription))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Renew the specified subscription.
     */
    public function renew(Subscription $subscription): JsonResponse
    {
        $this->authorize('update', $subscription);

        // Check if subscription can be renewed
        if ($subscription->isActive()) {
            return response()->json([
                'message' => 'Subscription is already active.',
            ], 422);
        }

        // Calculate new period
        $newPeriodStart = now();
        $newPeriodEnd = now()->addMonth(); // Default to monthly, can be adjusted based on plan

        // Update subscription status
        $subscription->update([
            'status' => 'active',
            'current_period_start' => $newPeriodStart,
            'current_period_end' => $newPeriodEnd,
            'cancelled_at' => null,
            'cancel_at' => null,
        ]);

        activity()
            ->performedOn($subscription)
            ->causedBy(auth()->user())
            ->log('Subscription renewed');

        // TODO: Call Stripe API to renew subscription
        // if ($subscription->stripe_subscription_id) {
        //     \Stripe\Subscription::update($subscription->stripe_subscription_id, [
        //         'cancel_at_period_end' => false,
        //     ]);
        // } else {
        //     // Create new Stripe subscription
        // }

        $subscription->load(['tenant']);

        return (new SubscriptionResource($subscription))
            ->response()
            ->setStatusCode(200);
    }
}
