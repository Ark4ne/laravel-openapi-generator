<?php

namespace Ark4ne\OpenApi\Parsers\Responses\Concerns;

use Ark4ne\JsonApi\Descriptors\Describer;
use Ark4ne\JsonApi\Descriptors\Values\ValueArray;
use Ark4ne\JsonApi\Descriptors\Values\ValueBool;
use Ark4ne\JsonApi\Descriptors\Values\ValueDate;
use Ark4ne\JsonApi\Descriptors\Values\ValueFloat;
use Ark4ne\JsonApi\Descriptors\Values\ValueInteger;
use Ark4ne\JsonApi\Descriptors\Values\ValueString;

trait ValueType
{
    private function instanceof(mixed $value, string $class): bool
    {
        static $cache;

        $exist = $cache[$class] ??= class_exists($class);

        return $exist && $value instanceof $class;
    }

    public function isBool(mixed $value): bool
    {
        return is_bool($value) || $this->instanceof($this, ValueBool::class);
    }

    public function isInt(mixed $value): bool
    {
        return is_int($value) || $this->instanceof($value, ValueInteger::class);
    }

    public function isFloat(mixed $value): bool
    {
        return is_float($value) || $this->instanceof($value, ValueFloat::class);
    }

    public function isString(mixed $value): bool
    {
        return is_string($value) || $this->instanceof($value, ValueString::class);
    }

    public function isArray(mixed $value): bool
    {
        return $this->instanceof($value, ValueArray::class);
    }

    public function isDate(mixed $value): bool
    {
        return $this->instanceof($value, \DateTimeInterface::class) || $this->instanceof($value, ValueDate::class);
    }

    public function isDescriber(mixed $value): bool
    {
        return $this->instanceof($value, Describer::class);
    }
}
