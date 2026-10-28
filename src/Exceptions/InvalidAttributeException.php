<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Exceptions;

use InvalidArgumentException;

final class InvalidAttributeException extends InvalidArgumentException implements OpenApiGeneratorException
{
    /**
     * `#[Response] on App\Http\Controllers\UserController::show: resource and type are mutually exclusive`
     *
     * @param class-string $attribute
     * @param string $target class, or `Class::method`
     */
    public static function on(string $attribute, string $target, string $message): self
    {
        return new self();
    }
}
