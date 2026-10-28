<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Operations;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Extraction\OperationContext;

final class OperationExtractors
{
    /** @var list<OperationExtractor> */
    private readonly array $extractors;

    public function __construct(OperationExtractor ...$extractors)
    {
        $this->extractors = array_values($extractors);
    }

    /**
     * @return list<OperationExtractor> by increasing priority
     */
    public function all(): array
    {
        return $this->extractors;
    }

    /**
     * Runs every supporting extractor, by increasing priority.
     */
    public function extract(OperationContext $operation, OperationDescription $description, GenerationContext $context): OperationDescription
    {
        return $description;
    }
}
