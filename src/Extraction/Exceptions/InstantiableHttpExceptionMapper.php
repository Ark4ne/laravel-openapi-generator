<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Exceptions;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\ResponseDescription;
use ReflectionClass;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * An HTTP exception of the application that fixes its status in a constructor without required argument: built to
 * read `getStatusCode()`. One that needs arguments is left to the next mapper.
 */
final readonly class InstantiableHttpExceptionMapper implements ExceptionMapper
{
    public const PRIORITY = 100;

    public function __construct(
        private ErrorBodies $errors = new LaravelErrorBodies(),
    ) {}

    public function priority(): int
    {
        return self::PRIORITY;
    }

    public function supports(string $exception, GenerationContext $context): bool
    {
        if (! is_a($exception, HttpExceptionInterface::class, true)) {
            return false;
        }

        $class = new ReflectionClass($exception);

        return $class->isInstantiable() && ($class->getConstructor()?->getNumberOfRequiredParameters() ?? 0) === 0;
    }

    public function map(string $exception, GenerationContext $context): ?ResponseDescription
    {
        try {
            $instance = new $exception();
        } catch (Throwable) {
            return null;
        }

        return $instance instanceof HttpExceptionInterface ? ErrorSchemas::response($this->errors, $instance->getStatusCode(), null, $context) : null;
    }
}
