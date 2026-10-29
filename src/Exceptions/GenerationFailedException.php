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
    private Diagnostics $diagnostics;

    public static function fromDiagnostics(string $document, Diagnostics $diagnostics): self
    {
        $lines = array_map(strval(...), $diagnostics->all());
        $exception = new self(sprintf("The document %s cannot be generated:\n- %s", $document, implode("\n- ", $lines)));
        $exception->diagnostics = $diagnostics;

        return $exception;
    }

    public function diagnostics(): Diagnostics
    {
        return $this->diagnostics ?? new Diagnostics();
    }
}
