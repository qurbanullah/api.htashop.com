<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;

/**
 * Helper class for managing cache operations enforcing architectural boundaries.
 */
class CacheHelper
{
    /**
     * Remember a value in cache, utilizing tags if the driver supports it.
     */
    public static function remember(array|string $tags, string $key, int|\DateTimeInterface $ttl, \Closure $callback)
    {
        if ($ttl instanceof \DateTimeInterface) {
            $ttl = $ttl->getTimestamp() - now()->getTimestamp();
            $ttl = max(0, $ttl);
        }

        if (config('cache.default') === 'redis' || config('cache.default') === 'memcached') {
            return Cache::tags($tags)->remember($key, $ttl, $callback);
        }
        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Store a value in cache with tags.
     */
    public static function put(array|string $tags, string $key, $value, int|\DateTimeInterface $ttl)
    {
        if ($ttl instanceof \DateTimeInterface) {
            $ttl = $ttl->getTimestamp() - now()->getTimestamp();
            $ttl = max(0, $ttl);
        }

        if (config('cache.default') === 'redis' || config('cache.default') === 'memcached') {
            return Cache::tags($tags)->put($key, $value, $ttl);
        }
        return Cache::put($key, $value, $ttl);
    }

    /**
     * Get a value from cache.
     */
    public static function get(array|string $tags, string $key, mixed $default = null)
    {
        if (config('cache.default') === 'redis' || config('cache.default') === 'memcached') {
            return Cache::tags($tags)->get($key, $default);
        }
        return Cache::get($key, $default);
    }

    /**
     * Forget a value from cache.
     */
    public static function forget(array|string $tags, string $key)
    {
        if (config('cache.default') === 'redis' || config('cache.default') === 'memcached') {
            return Cache::tags($tags)->forget($key);
        }
        return Cache::forget($key);
    }

    /**
     * Clear by tags (very efficient on Redis).
     */
    public static function clearTags(array|string $tags): void
    {
        if (config('cache.default') === 'redis' || config('cache.default') === 'memcached') {
            Cache::tags($tags)->flush();
        } else {
            // Fallback for drivers that don't support tags
            // We just clear everything safely or no-op if risky.
        }
    }

    /**
     * Clear cache by pattern or prefix (Legacy/Non-Tag support)
     */
    public static function clearByPattern(string $pattern): void
    {
        $keys = self::getKeysByPattern($pattern);
        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Clear specific cache keys
     */
    public static function clearKeys(array $keys): void
    {
        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Clear all caches for a specific model/resource
     */
    public static function clearResource(string $resource): void
    {
        $patterns = ["{$resource}:*", "{$resource}_*"];
        foreach ($patterns as $pattern) {
            self::clearByPattern($pattern);
        }
    }

    public static function clearGroup(string $group): void
    {
        self::clearByPattern("{$group}:*");
    }

    public static function clearPattern(string $pattern): void
    {
        self::clearByPattern($pattern);
    }

    /**
     * Get all cache keys matching a pattern
     */
    protected static function getKeysByPattern(string $pattern): array
    {
        if (config('cache.default') === 'redis') {
            return self::getRedisKeys($pattern);
        }
        return [];
    }

    /**
     * Get Redis keys by pattern
     */
    protected static function getRedisKeys(string $pattern): array
    {
        try {
            $store = Cache::getStore();
            $redis = $store->connection();

            $redisPrefix = config('database.redis.options.prefix', '');
            $cachePrefix = $store->getPrefix();
            $fullPrefix  = $redisPrefix . $cachePrefix;

            $keys = $redis->keys($cachePrefix . $pattern);

            return array_map(function ($key) use ($fullPrefix) {
                return $fullPrefix !== '' ? str_replace($fullPrefix, '', $key) : $key;
            }, $keys);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Clear all cache
     */
    public static function flush(): bool
    {
        return Cache::flush();
    }
}
