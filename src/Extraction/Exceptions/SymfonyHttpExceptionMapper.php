<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Exceptions;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\LengthRequiredHttpException;
use Symfony\Component\HttpKernel\Exception\LockedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;
use Symfony\Component\HttpKernel\Exception\PreconditionRequiredHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

/**
 * The HTTP exceptions of Symfony whose status is fixed by their class.
 */
final readonly class SymfonyHttpExceptionMapper implements ExceptionMapper
{
    public const PRIORITY = 200;

    /**
     * Exception class → status.
     */
    private const STATUSES = [
        AccessDeniedHttpException::class => 403,
        BadRequestHttpException::class => 400,
        ConflictHttpException::class => 409,
        GoneHttpException::class => 410,
        LengthRequiredHttpException::class => 411,
        LockedHttpException::class => 423,
        MethodNotAllowedHttpException::class => 405,
        NotAcceptableHttpException::class => 406,
        NotFoundHttpException::class => 404,
        PreconditionFailedHttpException::class => 412,
        PreconditionRequiredHttpException::class => 428,
        ServiceUnavailableHttpException::class => 503,
        TooManyRequestsHttpException::class => 429,
        UnauthorizedHttpException::class => 401,
        UnprocessableEntityHttpException::class => 422,
        UnsupportedMediaTypeHttpException::class => 415,
    ];

    public function __construct(
        private ErrorBodies $errors = new LaravelErrorBodies(),
    ) {}

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

        return $status === null ? null : ErrorSchemas::response($this->errors, $status, null, $context);
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
