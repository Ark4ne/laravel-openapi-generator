<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Support;

use ReflectionProperty;

/**
 * Reads the protected state of framework objects (rule objects) that expose no getter.
 */
final class Properties
{
    /**
     * The value of a property, whatever its visibility; `null` when the object has no such property.
     */
    public static function read(?object $object, string $property): mixed
    {
        return $object !== null && property_exists($object, $property) ? (new ReflectionProperty($object, $property))->getValue($object) : null;
    }
}
