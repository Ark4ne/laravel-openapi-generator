<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Exceptions;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApiWriter\Objects\Schema;

/**
 * The bodies of the error responses, those of the declared exceptions and those the passes add: replaced by an
 * integration whose errors have another shape (JSON:API).
 */
interface ErrorBodies
{
    /**
     * The media type of the error responses.
     */
    public function mediaType(): string;

    public function error(GenerationContext $context): Schema;

    public function validation(GenerationContext $context): Schema;
}
