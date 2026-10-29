<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Sampling;

use Ark4ne\OpenApi\Attributes\SchemaName;
use ReflectionClass;

/**
 * The preferred component name of a class: its `#[SchemaName]`, else its basename without `Resource`.
 */
final class ComponentNames
{
    /**
     * @param class-string $class
     */
    public static function of(string $class): string
    {
        $attribute = (new ReflectionClass($class))->getAttributes(SchemaName::class)[0] ?? null;

        if ($attribute !== null) {
            return $attribute->newInstance()->name;
        }

        $basename = substr($class, (int) strrpos('\\' . $class, '\\'));

        return str_ends_with($basename, 'Resource') && $basename !== 'Resource' ? substr($basename, 0, -8) : $basename;
    }
}
