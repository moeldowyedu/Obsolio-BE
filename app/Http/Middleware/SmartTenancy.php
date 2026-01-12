<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Tenancy;
use App\Models\Tenant;

/**
 * Smart Tenancy Middleware
 * 
 * Identifies tenant from:
 * 1. Domain/subdomain (if not on central domain)
 * 2. JWT user's tenant_id (if on central domain)
 */
class SmartTenancy
{
    public function handle(Request $request, Closure $next)
    {
        $centralDomains = config('tenancy.central_domains', []);
        $host = $request->getHost();

        // If on central domain, try to get tenant from authenticated user
        if (in_array($host, $centralDomains)) {
            $user = auth()->user();

            if ($user && $user->tenant_id) {
                $tenant = Tenant::find($user->tenant_id);

                if ($tenant) {
                    tenancy()->initialize($tenant);
                }
            }

            return $next($request);
        }

        // If not on central domain, use standard domain-based tenancy
        return app(\Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class)->handle($request, $next);
    }
}
