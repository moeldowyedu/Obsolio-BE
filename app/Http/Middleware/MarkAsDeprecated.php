<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MarkAsDeprecated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $newEndpoint = '', string $version = 'v2.0'): Response
    {
        $response = $next($request);

        // Add deprecation headers
        $response->headers->set('X-API-Deprecated', 'true');

        if ($newEndpoint) {
            $response->headers->set(
                'X-API-Deprecation-Info',
                "Use {$newEndpoint} instead. This endpoint will be removed in {$version}"
            );
        } else {
            $response->headers->set(
                'X-API-Deprecation-Info',
                "This endpoint is deprecated and will be removed in {$version}"
            );
        }

        return $response;
    }
}
