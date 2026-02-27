<?php

namespace Ark4ne\OpenApi\Support;

use Illuminate\Support\Str;

class Ref
{
    /**
     * @param string|class-string $enumName
     * @return string
     */
    public static function enumRef(string $enumName): string
    {
        if (class_exists($enumName)) {
            if ($customId = self::resolveCustomId($enumName)) {
                return 'enum-' . self::snake($customId);
            }

            $enumReflection = Reflection::reflection($enumName);
            $key = substr(md5($enumReflection->getNamespaceName()), 0, 6) . '-' . $enumReflection->getShortName();

            return 'enum-' . self::snake($key);
        }

        return "enum-" . self::snake($enumName);
    }

    /**
     * @param string|class-string $resourceRef
     * @return string
     */
    public static function resourceRef(string $resourceRef): string
    {
        if (class_exists($resourceRef)) {
            if ($customId = self::resolveCustomId($resourceRef)) {
                return 'resource-' . self::snake($customId);
            }

            $resourceReflection = Reflection::reflection($resourceRef);
            $key = substr(md5($resourceReflection->getNamespaceName()), 0, 6) . '-' . $resourceReflection->getShortName();

            return 'resource-' . self::snake($key);
        }

        return "resource-" . self::snake($resourceRef);
    }

    public static function fieldsRef(string $key): string
    {
        $key = self::snake($key);

        return 'rule-fields-' . $key;
    }

    public static function includeRef(string $key): string
    {
        $key = self::snake($key);

        return 'rule-include-' . $key;
    }

    public static function securityRef(string $key): string
    {
        $key = self::snake($key);

        return 'security-' . $key;
    }

    private static function resolveCustomId(string $class): ?string
    {
        $reflection = Reflection::reflection($class);
        $attributes = $reflection->getAttributes(\Ark4ne\OpenApi\Attributes\Id::class);

        if (empty($attributes)) {
            return null;
        }

        $id = $attributes[0]->newInstance()->id;

        $registry = ArrayCache::get([self::class, 'registry']) ?? [];

        if (isset($registry[$id]) && $registry[$id] !== $class) {
            throw new \RuntimeException(
                "OpenApi id \"{$id}\" is already claimed by {$registry[$id]}, conflict with {$class}."
            );
        }

        $registry[$id] = $class;
        ArrayCache::set([self::class, 'registry'], $registry);

        return $id;
    }

    private static function snake(string $value): string
    {
        return preg_replace('/-{2,}/', '-', Str::snake($value, '-'));
    }
}
