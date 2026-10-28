<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Attributes;

use Attribute;

/**
 * Overrides what the generator reads about an operation.
 *
 * On a controller class, the values apply to all its actions; on an action, they override the class.
 * A summary or a description may be a translation key.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class Operation
{
    /**
     * @param list<string> $tags replace the tags given by the tag strategy
     * @param string|null $externalDocs URL of the external documentation
     * @param string|null $externalDocsDescription its description (CommonMark); ignored without `externalDocs`
     */
    public function __construct(
        public ?string $summary = null,
        public ?string $description = null,
        public ?string $operationId = null,
        public array $tags = [],
        public ?bool $deprecated = null,
        public ?string $externalDocs = null,
        public ?string $externalDocsDescription = null,
    ) {}
}
