<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Exceptions;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\ResponseDescription;

/**
 * Tells the response Laravel renders for an exception. Mappers are tried by decreasing priority; the first that
 * supports the exception gives its response.
 */
interface ExceptionMapper
{
    public function priority(): int;

    /**
     * @param class-string $exception
     */
    public function supports(string $exception, GenerationContext $context): bool;

    /**
     * @param class-string $exception
     */
    public function map(string $exception, GenerationContext $context): ?ResponseDescription;
}
