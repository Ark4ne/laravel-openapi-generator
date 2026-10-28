<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Exceptions;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\RecordsNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Routing\Exceptions\BackedEnumCaseNotFoundException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;

/**
 * The exceptions Laravel turns into responses (`Handler::prepareException()`), subclasses included.
 */
final readonly class LaravelExceptionMapper implements ExceptionMapper
{
    public const PRIORITY = 300;

    /**
     * Exception class → status, as Laravel renders it.
     */
    private const STATUSES = [
        AuthenticationException::class => 401,
        AuthorizationException::class => 403,
        ModelNotFoundException::class => 404,
        RecordsNotFoundException::class => 404,
        BackedEnumCaseNotFoundException::class => 404,
        TokenMismatchException::class => 419,
        ValidationException::class => 422,
        ThrottleRequestsException::class => 429,
    ];

    public function priority(): int
    {
        return self::PRIORITY;
    }

    public function supports(string $exception, GenerationContext $context): bool
    {
        return self::status($exception) !== null;
    }

    public function map(string $exception, GenerationContext $context): ?ResponseDescription
    {
        $status = self::status($exception);

        return $status === null ? null : ErrorSchemas::response($status, null, $context, is_a($exception, ValidationException::class, true));
    }

    private static function status(string $exception): ?int
    {
        foreach (self::STATUSES as $class => $status) {
            if (is_a($exception, $class, true)) {
                return $status;
            }
        }

        return null;
    }
}
