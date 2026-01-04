<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class CheckExecutionQuota
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $subscription = $request->tenant_subscription ??
            $request->user()?->currentTenant?->activeSubscription;

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No subscription found'
            ], 403);
        }

        // Check if quota exceeded
        if ($subscription->hasExceededQuota()) {
            $plan = $subscription->plan;

            // If plan allows overage, let it through (will be billed)
            if ($plan && $plan->hasOveragePricing()) {
                // Log warning but allow
                Log::warning('Tenant exceeded quota but has overage pricing', [
                    'tenant_id' => $subscription->tenant_id,
                    'quota' => $subscription->execution_quota,
                    'used' => $subscription->executions_used,
                ]);

                return $next($request);
            }

            // No overage allowed - block execution
            return response()->json([
                'success' => false,
                'message' => 'Execution quota exceeded',
                'quota' => $subscription->execution_quota,
                'used' => $subscription->executions_used,
                'remaining' => 0,
                'action_required' => 'upgrade_plan'
            ], 429);
        }

        return $next($request);
    }
}
