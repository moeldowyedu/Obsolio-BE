<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * CDN Headers Middleware
 *
 * Adds appropriate cache headers and CDN configuration
 * for static assets and API responses
 */
class CDNHeaders
{
    /**
     * Handle an incoming request
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only add headers for successful responses
        if ($response->status() !== 200) {
            return $response;
        }

        // Handle static assets
        if ($this->isStaticAsset($request)) {
            $this->addStaticAssetHeaders($response, $request);
        }

        // Handle API responses
        if ($request->is('api/*')) {
            $this->addAPIHeaders($response);
        }

        // Add security headers
        $this->addSecurityHeaders($response);

        // Add CDN-specific headers
        $this->addCDNHeaders($response, $request);

        return $response;
    }

    /**
     * Check if request is for a static asset
     */
    private function isStaticAsset(Request $request): bool
    {
        if ($request->is('api/*')) {
            return false;
        }

        $extension = strtolower(pathinfo($request->path(), PATHINFO_EXTENSION));

        $staticExtensions = array_merge(
            config('cdn.asset_types.images.extensions', []),
            config('cdn.asset_types.styles.extensions', []),
            config('cdn.asset_types.scripts.extensions', []),
            config('cdn.asset_types.fonts.extensions', []),
            config('cdn.asset_types.videos.extensions', []),
            config('cdn.asset_types.documents.extensions', [])
        );

        return in_array($extension, $staticExtensions);
    }

    /**
     * Add headers for static assets
     */
    private function addStaticAssetHeaders($response, Request $request): void
    {
        $extension = strtolower(pathinfo($request->path(), PATHINFO_EXTENSION));
        $cacheControl = $this->getCacheControlForAsset($extension);

        if ($cacheControl) {
            $response->headers->set('Cache-Control', $cacheControl);
        }

        // Add ETag for efficient caching
        if (!$response->headers->has('ETag') && $response instanceof BinaryFileResponse) {
            $etag = md5($response->getFile()->getMTime() . $response->getFile()->getSize());
            $response->setEtag($etag);
            $response->isNotModified($request);
        }

        // Add Vary header
        $response->headers->set('Vary', 'Accept-Encoding');

        // Add CORS headers for fonts
        if (in_array($extension, config('cdn.asset_types.fonts.extensions', []))) {
            $this->addCORSHeaders($response);
        }
    }

    /**
     * Add headers for API responses
     */
    private function addAPIHeaders($response): void
    {
        // API responses should not be cached by CDN by default
        $response->headers->set('Cache-Control', 'private, no-cache, no-store, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        // Add Vary header for content negotiation
        $response->headers->set('Vary', 'Accept, Accept-Encoding, Authorization');
    }

    /**
     * Add security headers
     */
    private function addSecurityHeaders($response): void
    {
        $securityHeaders = config('cdn.security_headers', []);

        foreach ($securityHeaders as $header => $value) {
            if (!$response->headers->has($header)) {
                $response->headers->set($header, $value);
            }
        }
    }

    /**
     * Add CDN-specific headers
     */
    private function addCDNHeaders($response, Request $request): void
    {
        if (!config('cdn.enabled')) {
            return;
        }

        // Add CDN-Cache-Control for Cloudflare
        if (config('cdn.provider') === 'cloudflare' && $this->isStaticAsset($request)) {
            $ttl = config('cdn.providers.cloudflare.edge_cache_ttl', 7200);
            $response->headers->set('CDN-Cache-Control', "public, max-age={$ttl}");
        }

        // Add Surrogate-Control for Fastly
        if (config('cdn.provider') === 'fastly' && $this->isStaticAsset($request)) {
            $ttl = config('cdn.providers.fastly.default_ttl', 3600);
            $staleTtl = config('cdn.providers.fastly.stale_ttl', 86400);
            $response->headers->set('Surrogate-Control', "max-age={$ttl}, stale-while-revalidate={$staleTtl}");
        }

        // Add custom headers for debugging
        if (config('app.debug')) {
            $response->headers->set('X-CDN-Provider', config('cdn.provider'));
            $response->headers->set('X-CDN-Enabled', config('cdn.enabled') ? 'true' : 'false');
        }
    }

    /**
     * Add CORS headers
     */
    private function addCORSHeaders($response): void
    {
        $allowedOrigins = config('cdn.cors.allowed_origins', '*');
        $allowedMethods = implode(', ', config('cdn.cors.allowed_methods', ['GET']));
        $allowedHeaders = implode(', ', config('cdn.cors.allowed_headers', []));
        $maxAge = config('cdn.cors.max_age', 86400);

        $response->headers->set('Access-Control-Allow-Origin', $allowedOrigins);
        $response->headers->set('Access-Control-Allow-Methods', $allowedMethods);
        $response->headers->set('Access-Control-Allow-Headers', $allowedHeaders);
        $response->headers->set('Access-Control-Max-Age', $maxAge);
    }

    /**
     * Get cache control header for specific asset type
     */
    private function getCacheControlForAsset(string $extension): ?string
    {
        $assetTypes = config('cdn.asset_types', []);

        foreach ($assetTypes as $type => $config) {
            if (in_array($extension, $config['extensions'] ?? [])) {
                return $config['cache_control'] ?? null;
            }
        }

        return null;
    }
}
