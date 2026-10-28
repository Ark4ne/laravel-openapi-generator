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
        return new self();
    }
}
