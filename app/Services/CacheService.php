<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CacheService
{
    /**
     * Default cache TTL in seconds (1 hour)
     */
    private const DEFAULT_TTL = 3600;

    /**
     * Cache prefix for the application
     */
    private const CACHE_PREFIX = 'aasim:';

    /**
     * Get item from cache or execute callback and cache result
     */
    public static function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $fullKey = self::CACHE_PREFIX . $key;
        $ttl = $ttl ?? self::DEFAULT_TTL;

        try {
            return Cache::remember($fullKey, $ttl, $callback);
        } catch (\Exception $e) {
            Log::error('Cache remember failed', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            // If cache fails, execute callback directly
            return $callback();
        }
    }

    /**
     * Get item from cache
     */
    public static function get(string $key): mixed
    {
        $fullKey = self::CACHE_PREFIX . $key;

        try {
            return Cache::get($fullKey);
        } catch (\Exception $e) {
            Log::error('Cache get failed', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Put item in cache
     */
    public static function put(string $key, mixed $value, ?int $ttl = null): bool
    {
        $fullKey = self::CACHE_PREFIX . $key;
        $ttl = $ttl ?? self::DEFAULT_TTL;

        try {
            return Cache::put($fullKey, $value, $ttl);
        } catch (\Exception $e) {
            Log::error('Cache put failed', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Forget item from cache
     */
    public static function forget(string $key): bool
    {
        $fullKey = self::CACHE_PREFIX . $key;

        try {
            return Cache::forget($fullKey);
        } catch (\Exception $e) {
            Log::error('Cache forget failed', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Forget multiple items by pattern (using tags)
     */
    public static function forgetByPattern(string $pattern): bool
    {
        try {
            $tag = self::CACHE_PREFIX . $pattern;
            return Cache::tags([$tag])->flush();
        } catch (\Exception $e) {
            Log::error('Cache pattern forget failed', [
                'pattern' => $pattern,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Cache keys for tenant-specific data
     */
    public static function tenantKey(string $tenantId, string $key): string
    {
        return "tenant:{$tenantId}:{$key}";
    }

    /**
     * Cache keys for user-specific data
     */
    public static function userKey(string $userId, string $key): string
    {
        return "user:{$userId}:{$key}";
    }

    /**
     * Cache keys for organization-specific data
     */
    public static function orgKey(string $orgId, string $key): string
    {
        return "org:{$orgId}:{$key}";
    }

    /**
     * Cache keys for agent-specific data
     */
    public static function agentKey(string $agentId, string $key): string
    {
        return "agent:{$agentId}:{$key}";
    }

    /**
     * Invalidate all tenant cache
     */
    public static function invalidateTenant(string $tenantId): bool
    {
        return self::forgetByPattern("tenant:{$tenantId}");
    }

    /**
     * Invalidate all organization cache
     */
    public static function invalidateOrganization(string $orgId): bool
    {
        return self::forgetByPattern("org:{$orgId}");
    }
}
