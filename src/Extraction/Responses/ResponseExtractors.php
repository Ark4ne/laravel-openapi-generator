<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Responses;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApi\Types\Type;

final class ResponseExtractors
{
    /** @var list<ResponseExtractor> */
    private readonly array $extractors;

    public function __construct(ResponseExtractor ...$extractors)
    {
        $this->extractors = array_values($extractors);
    }

    /**
     * @return list<ResponseExtractor> by decreasing priority
     */
    public function all(): array
    {
        return $this->extractors;
    }

    /**
     * @return list<ResponseDescription> empty, with a diagnostic, when no extractor supports the type
     */
    public function extract(Type $type, OperationContext $operation, GenerationContext $context): array
    {
        return [];
    }
}
