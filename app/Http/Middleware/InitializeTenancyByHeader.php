<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\IdentificationMiddleware;
use Stancl\Tenancy\Tenancy;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancyByHeader extends IdentificationMiddleware
{
    /** @var callable|null */
    public static $onFail;

    /** @var Tenancy */
    protected $tenancy;

    public function __construct(Tenancy $tenancy)
    {
        $this->tenancy = $tenancy;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Priority: Check for X-Tenant-ID header
        if ($request->hasHeader('X-Tenant-ID')) {
            $tenantId = $request->header('X-Tenant-ID');
            $tenantId = $request->header('X-Tenant-ID');
            return $this->tryInitializeTenancy($tenantId, $request, $next);
        }

        // 2. Fallback: Check for Subdomain/Domain
        // If not on a central domain (localhost, 127.0.0.1, etc)
        $host = $request->getHost();
        $centralDomains = config('tenancy.central_domains', []);

        // Remove port if present involved in central domain check? 
        // Stancl usually handles this, but here we do a simple check.
        // Assuming production domains like 'obsolio.com' would also be in central_domains if we had them.

        $isCentral = false;
        foreach ($centralDomains as $central) {
            if ($host === $central || str_ends_with($host, '.' . $central)) {
                // Actually, if it ends with .central, it MIGHT be a subdomain, but if central is "obsolio.com", "iti.obsolio.com" IS a subdomain.
                // The logic: if host IS exactly a central domain, we are NOT in a tenant context (unless defined otherwise).
                // If host is "iti.obsolio.com" and "obsolio.com" is central, then "iti" is subdomain.
                if ($host === $central) {
                    $isCentral = true;
                    break;
                }
            }
        }

        // Simplified Logic: If we are on a subdomain (e.g. iti.localhost or iti.obsolio.com)
        // We try to extract the subdomain.
        $parts = explode('.', $host);

        // For localhost (iti.localhost), parts = ['iti', 'localhost'] -> count 2
        // For production (iti.obsolio.com), parts = ['iti', 'obsolio', 'com'] -> count 3
        // If we assumed 127.0.0.1 (no subdomain usually), count 4 (IPv4)

        // Better validation: rely on Stancl's domain identification? 
        // We can manually lookup tenant by ID using the first part of the host if it's not central.

        if (!$isCentral && count($parts) > 1 && $host !== 'localhost' && !in_array($host, $centralDomains)) {
            // Assume the first part is the tenant ID (since we use subdomain as ID)
            $potentialTenantId = $parts[0];

            // Try to find tenant
            $tenant = \App\Models\Tenant::find($potentialTenantId);
            if ($tenant) {
                $tenant = \App\Models\Tenant::find($potentialTenantId);
                if ($tenant) {
                    return $this->tryInitializeTenancy($potentialTenantId, $request, $next);
                }
            }
        }

        // 3. Fallback: Check Auth User (Last Resort - implicit context)
        // This handles the "I'm logged in, just show me my stuff" case on central domain
        // BUT, middleware ordering matters. Use this cautiously. 
        // For now, we stick to Header or Domain. 
        // The Controller fallback (User->tenant_id) handles the implicit case if middleware passes.

        return $next($request);
    }

    protected function tryInitializeTenancy($id, $request, $next)
    {
        try {
            $this->tenancy->initialize($id);
        } catch (\Exception $e) {
            if (static::$onFail) {
                return (static::$onFail)($e, $request, $next);
            }
            return response()->json(['message' => 'Tenant not found.', 'error' => $e->getMessage()], 404);
        }
        return $next($request);
    }
}
