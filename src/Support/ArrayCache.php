<?php

namespace Ark4ne\OpenApi\Support;

class ArrayCache
{
    /**
     * @var array<mixed>
     */
    private static array $cache = [];

    /**
     * Get a value from the cache.
     *
     * @param string[]|string $key
     * @return mixed|null
     */
    public static function get(string|array $key): mixed
    {
        return Arr::fetch(self::$cache, $key);
    }

    /**
     * Set a value in the cache.
     *
     * @template  T
     *
     * @param T $value
     * @param string[]|string $key
     * @return T
     */
    public static function set(string|array $key, mixed $value): mixed
    {
        Arr::apply(self::$cache, $key, $value);

        return $value;
    }

    /**
     * Check if a key exists in the cache.
     *
     * @param string[]|string $key
     * @return bool
     */
    public static function has(string|array $key): bool
    {
        return Arr::fetch(self::$cache, $key) !== null;
    }

    /**
     * @template T
     *
     * @param string|string[] $key
     * @param \Closure():T $value
     *
     * @return T
     */
    public static function fetch(string|array $key, \Closure $value): mixed
    {
        if (self::has($key)) {
            return self::get($key);
        }

        return self::set($key, $value());
    }

    /**
     * Clear the cache.
     *
     * @return void
     */
    public static function clear(): void
    {
        self::$cache = [];
    }
}
