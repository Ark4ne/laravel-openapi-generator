<?php

namespace Ark4ne\OpenApi\Support;

class Support
{
    /** @var array<class-string, array{method: array<string, bool>}> */
    private static array $supported = [];

    /**
     * @param class-string|object $object
     * @param string $method
     * @return bool
     */
    public static function method(string|object $object, string $method): bool
    {
        $class = is_string($object) ? $object : $object::class;

        return self::$supported[$class]['method'][$method] ??= method_exists($object, $method);
    }
    /**
     * @param class-string|object $object
     * @param string $property
     * @return bool
     */
    public static function property(string|object $object, string $property): bool
    {
        $class = is_string($object) ? $object : $object::class;

        return self::$supported[$class]['property'][$property] ??= property_exists($object, $property);
    }
}
