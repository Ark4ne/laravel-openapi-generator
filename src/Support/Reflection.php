<?php

namespace Ark4ne\OpenApi\Support;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionUnionType;

class Reflection
{

    /**
     * @template T of class-string
     *
     * @param \ReflectionType $type
     * @param null|T          $for
     *
     * @return array<class-string|T>
     */
    public static function parseTypeHint(\ReflectionType $type, ?string $for = null): ?string
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

        return in_array($type, ['bool', 'int', 'float', 'string', 'array', 'object', 'iterable', 'callable'])
            ? null
            : $map($type);
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
        $reflected = (new ReflectionClass($object))->getMethod($method);
        $reflected->setAccessible(true);

        return $reflected->invokeArgs(is_string($object) ? null : $object, $args);
    }
}
