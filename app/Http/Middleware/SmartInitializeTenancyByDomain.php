<?php

namespace App\Http\Middleware;

use Closure;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;

class SmartInitializeTenancyByDomain extends InitializeTenancyByDomain
{
    public function handle($request, Closure $next)
    {
        // Check if current host is in central domains
        $centralDomains = config('tenancy.central_domains', []);

        if (in_array($request->getHost(), $centralDomains)) {
            // If on central domain, bypass tenancy initialization
            return $next($request);
        }

        return parent::handle($request, $next);
    }
}
