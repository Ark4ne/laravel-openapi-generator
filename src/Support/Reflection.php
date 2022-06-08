<?php

namespace Ark4ne\OpenApi\Support;

use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlock\Tags\TagWithType;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\Types\ContextFactory;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use Reflector;

class Reflection
{
    public static function reflection(string|object $class): ReflectionClass
    {
        static $reflects;

        return $reflects[is_string($class) ? $class : get_class($class)] ??= new ReflectionClass($class);
    }

    public static function method(string|object $class, string $method): ReflectionMethod
    {
        return self::reflection($class)->getMethod($method);
    }

    public static function docComment(Reflector $reflector): ?string
    {
        if (!method_exists($reflector, 'getDocComment')) {
            return null;
        }

        return $reflector->getDocComment();
    }

    public static function docblock(Reflector $reflector): ?DocBlock
    {
        static $factory, $contextFactory;

        if (!($doc = $reflector->getDocComment())) {
            return null;
        }

        $factory ??= DocBlockFactory::createInstance();
        $contextFactory ??= new ContextFactory();


        return $doc
            ? $factory->create($doc, $contextFactory->createFromReflector($reflector))
            : null;
    }

    public static function isBuiltin(string $class): bool
    {
        return in_array($class, ['bool', 'int', 'float', 'string', 'array', 'object', 'iterable', 'callable']);
    }

    public static function isInstantiable(string $class): bool
    {
        try {
            return self::reflection($class)->isInstantiable();
        } catch (\ReflectionException $e) {
            return false;
        }
    }

    public static function typeIsInstantiable(null|ReflectionType $type): bool
    {
        return $type instanceof ReflectionNamedType && !$type->isBuiltin() && self::isInstantiable($type->getName());
    }

    public static function parseReturnType(ReflectionMethod $method): ?string
    {
        $type = $method->getReturnType();

        $returnType = self::typeIsInstantiable($type)
            ? $type?->getName()
            : null;

        if (!($docblock = self::docblock($method))) {
            return $returnType;
        }

        $tags = $docblock->getTagsWithTypeByName('return');
        $tag = $tags[0];

        $type = $tag->getType()?->__toString();

        $type = trim($type ?? '', '\\');

        if ($type === $returnType || !$type || self::isBuiltin($type) || !self::isInstantiable($type)) {
            return $returnType;
        }

        if (is_subclass_of($returnType, $type)) {
            return $type;
        }

        return $returnType;
    }

    public static function parseParametersFromDocBlockForClass(ReflectionMethod $method, string $for): ?string
    {
        if (!($docblock = self::docblock($method))) {
            return null;
        }

        $tags = $docblock->getTagsWithTypeByName('params');

        foreach ($tags as $tag) {
            if (is_subclass_of($tag->getType(), $for)) {
                return $tag->getType();
            }
        }

        return null;
    }

    /**
     * @template T
     *
     * @param \ReflectionType      $type
     * @param class-string<T>|null $for
     *
     * @return class-string<T>|class-string|null
     */
    public static function parseTypeHint(ReflectionType $type, ?string $for = null): ?string
    {
        if ($type instanceof ReflectionUnionType) {
            return null;
        }

        $map = static fn(string $type): ?string => $for
            ? (is_subclass_of($type, $for) ? $type : null)
            : $type;

        if ($type instanceof ReflectionNamedType) {
            return $type->isBuiltin() ? null : $map($type->getName());
        }

        /** @noinspection CallableParameterUseCaseInTypeContextInspection */
        $type = $type->__toString();

        return self::isBuiltin($type) ? null : $map($type);
    }

    /**
     * @param string|object $object
     * @param string        $method
     * @param array<mixed>  ...$args
     *
     * @throws \ReflectionException
     * @return mixed
     */
    public static function call(string|object $object, string $method, array ...$args): mixed
    {
        $reflected = self::reflection($object)->getMethod($method);
        $reflected->setAccessible(true);

        return $reflected->invokeArgs(is_string($object) ? null : $object, $args);
    }

    public static function getPropertyType(mixed $class, string $property, bool $onlyInstantiable = false): ?string
    {
        if ($type = self::getPropertyTypeFromProperties(self::reflection($class), $property, $onlyInstantiable)) {
            return $type;
        }
        if ($type = self::getPropertyTypeFromClass(self::reflection($class), $property, $onlyInstantiable)) {
            return $type;
        }

        return null;
    }

    protected static function getPropertyTypeFromProperties(
        ReflectionClass $class,
        string $prop,
        bool $onlyInstantiable
    ): ?string {
        try {
            $property = $class->getProperty($prop);
        } catch (\ReflectionException $e) {
            return null;
        }

        if ($onlyInstantiable && self::typeIsInstantiable($type = $property->getType())) {
            return $type->getName();
        }

        if ($type instanceof ReflectionNamedType) {
            return $type->getName();
        }

        if ($docblock = self::docblock($property)) {
            $tags = $docblock->getTagsWithTypeByName('var');
            $tag = $tags[0];

            if (($type = (string)$tag->getType()) && (!$onlyInstantiable || self::isInstantiable($type))) {
                return $type;
            }
        }
        return null;
    }

    protected static function getPropertyTypeFromClass(
        ReflectionClass $class,
        string $property,
        bool $onlyInstantiable
    ): ?string {
        if (!($docblock = self::docblock($class))) {
            return null;
        }

        /** @var TagWithType|null $tag */
        $tag = collect($docblock->getTagsWithTypeByName('property'))
            ->merge($docblock->getTagsWithTypeByName('property-read'))
            ->first(
                fn(TagWithType $value) => $value->getName() === $property
            );

        if ($tag && ($type = (string)$tag->getType()) && (!$onlyInstantiable || self::isInstantiable($type))) {
            return $type;
        }

        return null;
    }
}
