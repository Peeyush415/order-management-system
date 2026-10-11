<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Versioned cache keys: "dashboard:v7:summary:2026-09-11:2026-10-10".
 *
 * Bumping a version makes every key built on it unreachable at once, so related
 * entries are invalidated without cache tags. That works on every driver
 * (file, database, redis); old entries simply expire with their TTL.
 */
class CacheVersion
{
    public static function current(string $name): int
    {
        return (int) Cache::rememberForever(self::versionKey($name), fn () => 1);
    }

    public static function bump(string $name): int
    {
        Cache::add(self::versionKey($name), 1);

        return (int) Cache::increment(self::versionKey($name));
    }

    public static function key(string $name, string ...$parts): string
    {
        return implode(':', [$name, 'v'.self::current($name), ...$parts]);
    }

    /**
     * @param  list<string>  $parts
     */
    public static function remember(string $name, array $parts, int $ttlSeconds, Closure $callback): mixed
    {
        return Cache::remember(self::key($name, ...$parts), $ttlSeconds, $callback);
    }

    private static function versionKey(string $name): string
    {
        return "cache-version:{$name}";
    }
}
