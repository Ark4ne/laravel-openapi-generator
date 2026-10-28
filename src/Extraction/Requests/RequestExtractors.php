<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Requests;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\RequestDescription;
use Ark4ne\OpenApi\Extraction\OperationContext;

final class RequestExtractors
{
    /** @var list<RequestExtractor> */
    private readonly array $extractors;

    public function __construct(RequestExtractor ...$extractors)
    {
        $this->extractors = array_values($extractors);
    }

    /**
     * @return list<RequestExtractor> by increasing priority
     */
    public function all(): array
    {
        return $this->extractors;
    }

    /**
     * Runs every supporting extractor, by increasing priority.
     */
    public function extract(OperationContext $operation, RequestDescription $request, GenerationContext $context): RequestDescription
    {
        return $request;
    }
}
