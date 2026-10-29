<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Exceptions;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApiWriter\Objects\Schema;

/**
 * Laravel's JSON errors: `Error` (`message`), `ValidationError` (`message`, `errors`).
 */
final readonly class LaravelErrorBodies implements ErrorBodies
{
    public function mediaType(): string
    {
        return 'application/json';
    }

    public function error(GenerationContext $context): Schema
    {
        return ErrorSchemas::error($context);
    }

    public function validation(GenerationContext $context): Schema
    {
        return ErrorSchemas::validation($context);
    }
}
