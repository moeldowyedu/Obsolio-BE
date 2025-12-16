<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Tenancy;
use Symfony\Component\HttpFoundation\Response;

/**
 * Initialize Tenancy by X-Tenant-ID Header
 * 
 * This middleware is for API clients (mobile apps, third-party integrations)
 * that cannot use subdomain-based tenant detection and must send tenant ID in header.
 * 
 * For web applications, use InitializeTenancyByDomain instead.
 */
class InitializeTenancyByHeader
{
    protected $tenancy;

    public function __construct(Tenancy $tenancy)
    {
        $this->tenancy = $tenancy;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check for X-Tenant-ID header (for API clients, mobile apps)
        if ($request->hasHeader('X-Tenant-ID')) {
            $tenantId = $request->header('X-Tenant-ID');

            try {
                // Initialize tenant context
                $this->tenancy->initialize($tenantId);

            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or inactive tenant',
                    'error' => 'The provided tenant ID does not exist or is not active.',
                ], 404);
            }
        }

        // If no header provided, continue (will be handled by domain-based detection)
        return $next($request);
    }
}
