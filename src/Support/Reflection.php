<?php

namespace Ark4ne\OpenApi\Support;

use Ark4ne\OpenApi\Support\Reflection\Type;
use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlock\Tags\TagWithType;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\Types\Collection;
use phpDocumentor\Reflection\Types\Compound;
use phpDocumentor\Reflection\Types\ContextFactory;
use ReflectionEnum;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use Reflector;

class Reflection
{
    public static function reflection(string|object $class): ReflectionClass|ReflectionEnum
    {
        static $reflects;

        $classStr = is_string($class) ? $class : get_class($class);

        return $reflects[$classStr] ??= enum_exists($classStr)
            ? new ReflectionEnum($class)
            : new ReflectionClass($class);
    }

    public static function method(string|object $class, string $method): ReflectionMethod
    {
        return self::reflection($class)->getMethod($method);
    }

    public static function property(string|object $class, string $property): ReflectionProperty
    {
        $propertyReflection = self::reflection($class)->getProperty($property);
        $propertyReflection->setAccessible(true);

        return $propertyReflection;
    }

    public static function docComment(Reflector $reflector): ?string
    {
        if (!method_exists($reflector, 'getDocComment')) {
            return null;
        }

        return $reflector->getDocComment();
    }

    public static function parseDocComment(string $comment, Reflector $reflector): DocBlock
    {
        static $factory, $contextFactory;

        $factory ??= DocBlockFactory::createInstance();
        $contextFactory ??= new ContextFactory();

        return $factory->create($comment, $contextFactory->createFromReflector($reflector));
    }

    public static function docblock(Reflector $reflector): ?DocBlock
    {
        $doc = self::docComment($reflector);

        return $doc
            ? self::parseDocComment($doc, $reflector)
            : null;
    }

    protected static function up(Reflector $reflector): ?ReflectionClass
    {
        if ($reflector instanceof ReflectionMethod) {
            return $reflector->getDeclaringClass();
        }
        if ($reflector instanceof ReflectionProperty) {
            return $reflector->getDeclaringClass();
        }
        if ($reflector instanceof ReflectionClass) {
            return $reflector->getParentClass() ?: null;
        }

        return null;
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

    /**
     * @param \ReflectionMethod $method
     * @param bool $allowBuiltin
     *
     * @return \Ark4ne\OpenApi\Support\Reflection\Type[]|\Ark4ne\OpenApi\Support\Reflection\Type|null
     */
    public static function parseReturnType(ReflectionMethod $method, bool $allowBuiltin = false): null|Type|array
    {
        return self::parseType(
            $method->getReturnType(),
            $method,
            'return',
            allowBuiltin: $allowBuiltin
        );
    }

    /**
     * @param \ReflectionType|null $type
     * @param \Reflector $from
     * @param string $typeName
     * @param string|null $typeAccess
     * @param bool $allowBuiltin
     *
     * @return \Ark4ne\OpenApi\Support\Reflection\Type[]|\Ark4ne\OpenApi\Support\Reflection\Type|null
     */
    public static function parseType(
        ?ReflectionType $type,
        Reflector       $from,
        string          $typeName,
        null|string     $typeAccess = null,
        bool            $allowBuiltin = false
    ): null|Type|array
    {
        $trueType = self::parseTrueType($type, $from, $typeName, $typeAccess, $allowBuiltin);

        if (!($docblock = self::docblock($from))) {
            return $trueType;
        }

        $tags = $docblock->getTagsWithTypeByName($typeName);
        if (empty($tags)) {
            return $trueType;
        }
        if ($typeAccess) {
            $tag = null;
            foreach ($tags as $tag) {
                if ($tag->getName() === $typeAccess) {
                    break;
                }
                $tag = null;
            }
        } else {
            $tag = $tags[0];
        }

        if (!$tag) {
            return $trueType;
        }

        return self::parseDoctag($tag) ?? $trueType;
    }

    public static function parseTrueType(
        ?ReflectionType $type,
        Reflector       $from,
        string          $typeName,
        null|string     $typeAccess = null,
        bool            $allowBuiltin = false
    )
    {
        if ($type instanceof ReflectionUnionType) {
            return collect($type->getTypes())
                ->map(fn($unionType) => self::parseTrueType($unionType, $from, $typeName, $typeAccess, $allowBuiltin))
                ->filter()
                ->all();
        }

        $returnType = $allowBuiltin || self::typeIsInstantiable($type)
            ? $type?->getName()
            : null;

        return self::type($returnType)?->builtin(!self::typeIsInstantiable($type));
    }

    /**
     * @param \phpDocumentor\Reflection\DocBlock\Tag $tag
     * @param string|null $reflectionType
     * @param bool $allowBuiltin
     *
     * @return \Ark4ne\OpenApi\Support\Reflection\Type[]|\Ark4ne\OpenApi\Support\Reflection\Type|null
     */
    public static function parseDoctag(
        DocBlock\Tag $tag,
        ?string      $reflectionType = null,
        bool         $allowBuiltin = false
    ): null|array|Type
    {
        return self::parseDocType($tag->getType(), $reflectionType, $allowBuiltin);
    }

    public static function parseDocType(
        ?\phpDocumentor\Reflection\Type $type,
        ?string                         $reflectionType = null,
        bool                            $allowBuiltin = false
    )
    {
        if ($type instanceof Collection) {
            return Type::make($type->getFqsen())
                ->sub($type->getValueType())
                ->generic(true);
        }

        if ($type instanceof Compound) {
            return collect($type->getIterator())
                ->map(fn($type) => self::parseDocType($type, $reflectionType, $allowBuiltin))
                ->all();
        }

        $docType = $type?->__toString();

        $docType = trim($docType ?? '', '\\');

        if (!$docType || $docType === $reflectionType) {
            return null;
        }

        if ($allowBuiltin && self::isBuiltin($docType)) {
            return Type::make($docType, true);
        }

        if (!self::isInstantiable($docType)) {
            return null;
        }

        return Type::make($docType, false);
    }

    public static function parseParametersFromDocBlockForClass(ReflectionMethod $method, string $for): ?Type
    {
        if (!($docblock = self::docblock($method))) {
            return null;
        }

        $tags = $docblock->getTagsWithTypeByName('params');

        foreach ($tags as $tag) {
            if (is_subclass_of($tag->getType(), $for)) {
                return self::type($tag->getType());
            }
        }

        return null;
    }

    /**
     * @template T
     *
     * @param \ReflectionType $type
     * @param class-string<T>|null $for
     *
     * @return Type<T, null>|null
     */
    public static function parseTypeHint(ReflectionType $type, ?string $for = null): ?Type
    {
        if ($type instanceof ReflectionUnionType) {
            return null;
        }

        $map = static fn(string $type): ?string => $for
            ? (is_subclass_of($type, $for) ? $type : null)
            : $type;

        if ($type instanceof ReflectionNamedType) {
            return self::type($type->isBuiltin() ? null : $map($type->getName()));
        }

        $strType = $type->__toString();

        return self::type(self::isBuiltin($strType) ? null : $map($strType));
    }

    public static function getPropertyType(mixed $class, string $property, bool $allowBuiltin = true): ?Type
    {
        if ($type = self::getPropertyTypeFromProperties(self::reflection($class), $property, $allowBuiltin)) {
            return $type;
        }
        if ($type = self::getPropertyTypeFromClass(self::reflection($class), $property, $allowBuiltin)) {
            return $type;
        }

        return null;
    }

    protected static function getPropertyTypeFromProperties(
        ReflectionClass $class,
        string          $prop,
        bool            $allowBuiltin
    ): ?Type
    {
        try {
            $property = $class->getProperty($prop);
        } catch (\ReflectionException $e) {
            return null;
        }

        $type = $property->getType();

        if ($type && (($allowBuiltin && $type->isBuiltin()) || self::typeIsInstantiable($type))) {
            return Type::make($type->getName(), $type->isBuiltin());
        }

        if ($type instanceof ReflectionNamedType) {
            return Type::make($type->getName(), $type->isBuiltin());
        }

        if ($docblock = self::docblock($property)) {
            $tags = $docblock->getTagsWithTypeByName('var');
            $tag = $tags[0];

            if ($type = self::parseDoctag($tag, $type, $allowBuiltin)) {
                return $type;
            }
        }
        return null;
    }

    protected static function getPropertyTypeFromClass(
        ReflectionClass $class,
        string          $property,
        bool            $allowBuiltin
    ): ?Type
    {
        if (!($docblock = self::docblock($class))) {
            return null;
        }

        /** @var TagWithType|null $tag */
        $tag = collect($docblock->getTagsWithTypeByName('property'))
            ->merge($docblock->getTagsWithTypeByName('property-read'))
            ->first(
                fn(DocBlock\Tags\Property|DocBlock\Tags\PropertyRead $value) => $value->getVariableName() === $property
            );

        if ($tag && ($type = self::parseDoctag($tag, null, $allowBuiltin))) {
            return $type;
        }

        return null;
    }

    public static function tryParseGeneric(Reflector $reflector, string $fromTag): ?Type
    {
        do {
            $block = self::docblock($reflector);

            if (!empty($tags = $block?->getTagsByName($fromTag))) {
                /** @var DocBlock\Tags\BaseTag|DocBlock\Tags\TagWithType|DocBlock\Tags\Extends_ $tag */
                $tag = $tags[0];

                if ($tag instanceof DocBlock\Tags\Extends_) {
                    return self::parseDoctag($tag);
                }

                $description = $tag->getDescription()?->getBodyTemplate() ?? '';

                preg_match('/([\\\\\w]+)(?:<([\\\\\w]+)>)?/', $description, $matches);

                if (isset($matches[2])) {
                    $docblock[] = "/**";
                    $docblock[] = " * @param {$matches[2]} \$params";
                    $docblock[] = " */";

                    $block = self::parseDocComment(implode("\n", $docblock), $reflector);
                    $tags = $block->getTagsWithTypeByName('param');

                    if (!empty($tags)) {
                        return self::parseDoctag($tags[0]);
                    }
                }
            }
        } while ($reflector = self::up($reflector));

        return null;
    }

    public static function hasMethod(string|object $object, string $method): bool
    {
        try {
            return self::reflection($object)->hasMethod($method);
        } catch (\ReflectionException) {
            return false;
        }
    }

    /**
     * @param string|object $object
     * @param string $method
     * @param array<mixed> ...$args
     *
     * @return mixed
     * @throws \ReflectionException
     */
    public static function call(string|object $object, string $method, mixed ...$args): mixed
    {
        $reflected = self::reflection($object)->getMethod($method);
        $reflected->setAccessible(true);

        return $reflected->invokeArgs(is_string($object) ? null : $object, $args);
    }

    public static function read(string|object $object, string $property): mixed
    {
        $reflected = self::reflection($object)->getProperty($property);
        $reflected->setAccessible(true);

        return $reflected->getValue(is_string($object) ? null : $object);
    }

    protected static function type(?string $type): ?Type
    {
        return $type ? Type::make($type) : null;
    }
}
