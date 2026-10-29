<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Exceptions;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Ark4ne\OpenApi\Extraction\Responses\DeclaredResponses;

/**
 * The exception mappers: `#[Response]` of the exception → configuration → Laravel → Symfony → HttpException built.
 */
final class ExceptionMappers
{
    /** @var list<ExceptionMapper> by decreasing priority */
    private readonly array $mappers;

    public function __construct(ExceptionMapper ...$mappers)
    {
        $mappers = array_values($mappers);
        usort($mappers, static fn(ExceptionMapper $a, ExceptionMapper $b): int => $b->priority() <=> $a->priority());
        $this->mappers = $mappers;
    }

    /**
     * The built-in mappers, writing their errors with the given bodies.
     */
    public static function default(DeclaredResponses $declared, ErrorBodies $errors = new LaravelErrorBodies()): self
    {
        return new self(
            new AttributeExceptionMapper($declared, $errors),
            new ConfiguredExceptionMapper($errors),
            new LaravelExceptionMapper($errors),
            new SymfonyHttpExceptionMapper($errors),
            new InstantiableHttpExceptionMapper($errors),
        );
    }

    /**
     * @return list<ExceptionMapper> by decreasing priority
     */
    public function all(): array
    {
        return $this->mappers;
    }

    /**
     * The response of the first mapper that supports the exception and gives one.
     *
     * @param class-string $exception
     */
    public function map(string $exception, GenerationContext $context): ?ResponseDescription
    {
        foreach ($this->mappers as $mapper) {
            if ($mapper->supports($exception, $context)) {
                $response = $mapper->map($exception, $context);

                if ($response !== null) {
                    return $response;
                }
            }
        }

        return null;
    }
}
