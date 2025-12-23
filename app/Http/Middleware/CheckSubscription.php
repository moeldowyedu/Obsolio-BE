<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->user()->tenant;

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'No tenant found',
            ], 403);
        }

        $subscription = $tenant->activeSubscription;

        // Allow access if on trial or active
        if ($subscription && ($subscription->isOnTrial() || $subscription->isActive())) {
            return $next($request);
        }

        // Check if trial has expired
        if ($tenant->trial_ends_at && $tenant->trial_ends_at->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Trial period has ended. Please subscribe to continue.',
                'code' => 'TRIAL_EXPIRED',
            ], 402); // 402 Payment Required
        }

        return response()->json([
            'success' => false,
            'message' => 'Active subscription required',
            'code' => 'SUBSCRIPTION_REQUIRED',
        ], 402);
    }
}