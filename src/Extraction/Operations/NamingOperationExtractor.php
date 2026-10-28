<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Operations;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApi\Extraction\Strategies\OperationIdStrategy;
use Ark4ne\OpenApi\Extraction\Strategies\SummaryStrategy;
use Ark4ne\OpenApi\Extraction\Strategies\TagStrategy;

/**
 * Summary, tags and operationId given by the naming strategies of the document: the defaults the PHPDoc and the
 * attributes refine.
 */
final readonly class NamingOperationExtractor implements OperationExtractor
{
    public const PRIORITY = 0;

    public function __construct(
        private SummaryStrategy $summary,
        private TagStrategy $tags,
        private OperationIdStrategy $operationId,
    ) {}

    public function priority(): int
    {
        return self::PRIORITY;
    }

    public function supports(OperationContext $operation): bool
    {
        return true;
    }

    public function extract(OperationContext $operation, OperationDescription $description, GenerationContext $context): OperationDescription
    {
        return $description
            ->summary($this->summary->summary($operation) ?? $description->summary)
            ->tags(...$this->tags->tags($operation))
            ->operationId($this->operationId->operationId($operation) ?? $description->operationId);
    }
}
