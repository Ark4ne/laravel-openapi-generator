<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Operations;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Description\TagDescription;
use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApi\Extraction\Strategies\GroupStrategy;
use Ark4ne\OpenApi\Extraction\Strategies\OperationIdStrategy;
use Ark4ne\OpenApi\Extraction\Strategies\SummaryStrategy;
use Ark4ne\OpenApi\Extraction\Strategies\TagStrategy;

/**
 * Summary, tags and operationId given by the naming strategies of the document: the defaults the PHPDoc and the
 * attributes refine. The group strategy gives the parent of each tag (`Tag.parent`, `x-tagGroups` before 3.2).
 */
final readonly class NamingOperationExtractor implements OperationExtractor
{
    public const PRIORITY = 0;

    public function __construct(
        private SummaryStrategy $summary,
        private TagStrategy $tags,
        private OperationIdStrategy $operationId,
        private ?GroupStrategy $groups = null,
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
        $tags = $this->tags->tags($operation);
        $description = $description
            ->summary($this->summary->summary($operation) ?? $description->summary)
            ->tags(...$tags)
            ->operationId($this->operationId->operationId($operation) ?? $description->operationId);

        foreach ($tags as $tag) {
            $group = $this->groups?->group($tag, $operation);

            if ($group !== null) {
                $description = $description->addDeclaredTags(TagDescription::create($tag)->parent($group));
            }
        }

        return $description;
    }
}
