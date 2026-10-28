<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Exceptions;

use InvalidArgumentException;

final class InvalidConfigurationException extends InvalidArgumentException implements OpenApiGeneratorException
{
    /**
     * `openapi.documents.v1.openapi: expected one of 3.0.0, …, 3.2.0, got "3.3"`
     */
    public static function forKey(string $key, string $expected, mixed $received): self
    {
        return new self(sprintf('openapi.%s: expected %s, got %s', $key, $expected, self::describe($received)));
    }

    private static function describe(mixed $value): string
    {
        return match (true) {
            is_string($value) => "\"{$value}\"",
            is_int($value), is_float($value) => (string) $value,
            is_bool($value) => $value ? 'true' : 'false',
            default => get_debug_type($value),
        };
    }
}
