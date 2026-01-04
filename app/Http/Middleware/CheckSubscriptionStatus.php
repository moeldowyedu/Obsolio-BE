<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscriptionStatus
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->user()?->currentTenant;

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'No active tenant selected'
            ], 403);
        }

        $subscription = $tenant->activeSubscription;

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription found',
                'action_required' => 'subscribe'
            ], 403);
        }

        // Check if subscription is active
        if (!$subscription->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription is not active',
                'status' => $subscription->status,
                'action_required' => 'renew_subscription'
            ], 403);
        }

        // Attach subscription to request for controllers
        $request->merge(['tenant_subscription' => $subscription]);

        return $next($request);
    }
}
