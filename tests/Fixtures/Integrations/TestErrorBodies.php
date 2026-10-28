<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Integrations;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Generation\ErrorBodies;
use Ark4ne\OpenApiWriter\Objects\Schema;

/**
 * Error bodies of their own media type, all a reference to `Problem`.
 */
final readonly class TestErrorBodies implements ErrorBodies
{
    public function __construct(
        private string $mediaType = 'application/problem+json',
    ) {}

    public function mediaType(): string
    {
        return $this->mediaType;
    }

    public function error(GenerationContext $context): Schema
    {
        return Schema::ref('Problem');
    }

    public function validation(GenerationContext $context): Schema
    {
        return Schema::ref('Problem');
    }
}
