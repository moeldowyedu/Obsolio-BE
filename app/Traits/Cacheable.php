<?php

namespace App\Traits;

use App\Services\CacheService;

trait Cacheable
{
    /**
     * Boot the trait
     */
    protected static function bootCacheable(): void
    {
        // Clear cache on model events
        static::created(function ($model) {
            $model->clearModelCache();
        });

        static::updated(function ($model) {
            $model->clearModelCache();
        });

        static::deleted(function ($model) {
            $model->clearModelCache();
        });
    }

    /**
     * Get cached model by ID
     */
    public static function findCached(string $id, ?int $ttl = null)
    {
        $key = static::getCacheKey($id);

        return CacheService::remember($key, function () use ($id) {
            return static::find($id);
        }, $ttl ?? 3600);
    }

    /**
     * Get all records with cache
     */
    public static function allCached(?int $ttl = null)
    {
        $key = static::class . ':all';

        return CacheService::remember($key, function () {
            return static::all();
        }, $ttl ?? 1800); // 30 minutes
    }

    /**
     * Clear cache for this model
     */
    public function clearModelCache(): void
    {
        if (method_exists($this, 'getKey')) {
            CacheService::forget(static::getCacheKey($this->getKey()));
        }

        // Clear collection cache
        CacheService::forget(static::class . ':all');

        // Clear tenant cache if model has tenant_id
        if (isset($this->tenant_id)) {
            CacheService::invalidateTenant($this->tenant_id);
        }
    }

    /**
     * Get cache key for model instance
     */
    protected static function getCacheKey(string $id): string
    {
        return static::class . ':' . $id;
    }

    /**
     * Remember query results
     */
    public function scopeCached($query, string $key, ?int $ttl = null)
    {
        return CacheService::remember($key, function () use ($query) {
            return $query->get();
        }, $ttl ?? 3600);
    }
}
