<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApiWriter\Objects\Schema;

/**
 * The bodies of the error responses the passes add: replaced by an integration whose errors have another shape
 * (JSON:API in E9).
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
