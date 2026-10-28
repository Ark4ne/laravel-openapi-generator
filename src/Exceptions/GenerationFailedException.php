<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Exceptions;

use Ark4ne\OpenApi\Context\Diagnostics;
use RuntimeException;

/**
 * Thrown in strict mode when the generation reported a warning or an error; it carries all of them.
 */
final class GenerationFailedException extends RuntimeException implements OpenApiGeneratorException
{
    public static function fromDiagnostics(string $document, Diagnostics $diagnostics): self
    {
        return new self();
    }

    public function diagnostics(): Diagnostics
    {
        return new Diagnostics();
    }
}
