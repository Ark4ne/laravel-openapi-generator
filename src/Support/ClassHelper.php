<?php

namespace Ark4ne\OpenApi\Support;

class ClassHelper
{
    /**
     * @var array <class-string, bool>
     */
    private static array $cache = [];

    /**
     * @param mixed $object
     * @param class-string $class
     * @return bool
     */
    public static function isInstanceOf(mixed $object, string $class): bool
    {
        if (!is_object($object)) {
            return false;
        }

        if (self::classExist($class)) {
            return $object instanceof $class;
        }

        return false;
    }

    /**
     * @param class-string $class
     * @return bool
     */
    public static function classExist(string $class): bool
    {
        return self::$cache[$class] ??= class_exists($class) || interface_exists($class) || trait_exists($class);
    }
}
