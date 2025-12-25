<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();

        if (!$tenant) {
            return $next($request);
        }

        // Check if tenant is pending verification
        if ($tenant->status === 'pending_verification') {
            return response()->json([
                'success' => false,
                'message' => 'Workspace not found. Please verify your email to activate your workspace.',
                'code' => 'WORKSPACE_NOT_VERIFIED'
            ], 404);
        }

        // Check if tenant is suspended/inactive
        if ($tenant->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Workspace is suspended or inactive.',
                'code' => 'WORKSPACE_INACTIVE'
            ], 403);
        }

        // Check trial status
        if ($tenant->trial_ends_at && $tenant->trial_ends_at->isPast()) {
            // Check if there is an active subscription
            // Assuming 'activeSubscription' relationship or logic exists on Tenant model
            // Based on Tenant model view earlier, it has activeSubscription() relation

            if (!$tenant->activeSubscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'Trial period expired. Please upgrade your plan to continue.',
                    'code' => 'TRIAL_EXPIRED'
                ], 403);
            }
        }

        return $next($request);
    }
}
