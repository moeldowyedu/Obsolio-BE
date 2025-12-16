<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Tenant;

class CheckSubdomain
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $requiredType = null): Response
    {
        $host = $request->getHost();
        $parts = explode('.', $host);

        $isLocal = str_contains($host, 'localhost');
        $partCount = count($parts);

        // Identify if it's a central domain request
        // localhost (1 part) OR obsolio.com (2 parts) OR www.obsolio.com (3 parts, sub=www)
        if (
            ($isLocal && $partCount === 1) ||
            (!$isLocal && $partCount === 2) ||
            ($parts[0] === 'www') ||
            ($parts[0] === 'api')
        ) {
            // Special case: API requests from Console are "Admin" context
            $origin = $request->headers->get('origin');

            \Illuminate\Support\Facades\Log::info('CheckSubdomain Debug:', [
                'host' => $host,
                'parts' => $parts,
                'origin' => $origin,
                'matches_api' => $parts[0] === 'api',
                'matches_console_origin' => $origin && (str_contains($origin, '//console.') || str_contains($origin, '//admin.'))
            ]);

            if (
                ($parts[0] === 'api' || $parts[0] === 'localhost' || $isLocal) &&
                $origin &&
                (str_contains($origin, '//console.') || str_contains($origin, '//admin.') || str_contains($origin, 'console.localhost'))
            ) {
                $domainType = 'admin';
                $request->merge(['domain_type' => 'admin']);
            } else {
                $domainType = 'central';
                $request->merge(['domain_type' => 'central']);
            }
        } elseif ($parts[0] === 'console' || $parts[0] === 'admin') {
            $domainType = 'admin';
            $request->merge(['domain_type' => 'admin']);
        } else {
            // Tenant domain assumption
            $subdomain = $parts[0];
            $tenant = Tenant::find($subdomain);

            if (!$tenant) {
                // 404 if it looks like a tenant subdomain but no tenant found
                abort(404, 'Tenant not found');
            }

            try {
                tenancy()->initialize($tenant);
            } catch (\Exception $e) {
                abort(500, 'Tenant initialization error');
            }

            $domainType = 'tenant';
            $request->merge(['domain_type' => 'tenant', 'tenant' => $tenant]);
        }

        // Enforcement
        if ($requiredType && $domainType !== $requiredType) {
            abort(404); // Hide admin routes on other domains, etc.
        }

        return $next($request);
    }
}
