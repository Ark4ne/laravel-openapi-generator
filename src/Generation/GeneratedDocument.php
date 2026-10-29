<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApiWriter\Objects\OpenApi;

/**
 * A generated document: the writer's object, the description it comes from, and the context of its generation
 * (configuration, locale, diagnostics, components).
 */
final readonly class GeneratedDocument
{
    public function __construct(
        public OpenApi $openApi,
        public DocumentDescription $description,
        public GenerationContext $context,
    ) {}
}
