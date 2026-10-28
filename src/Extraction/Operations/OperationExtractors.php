<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Operations;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApi\Extraction\Strategies\OperationIdStrategy;
use Ark4ne\OpenApi\Extraction\Strategies\SummaryStrategy;
use Ark4ne\OpenApi\Extraction\Strategies\TagStrategy;
use Illuminate\Contracts\Translation\Translator;

final class OperationExtractors
{
    /** @var list<OperationExtractor> */
    private readonly array $extractors;

    public function __construct(OperationExtractor ...$extractors)
    {
        $extractors = array_values($extractors);
        usort($extractors, static fn(OperationExtractor $a, OperationExtractor $b): int => $a->priority() <=> $b->priority());
        $this->extractors = $extractors;
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
        foreach ($this->extractors as $extractor) {
            if ($extractor->supports($operation)) {
                $description = $extractor->extract($operation, $description, $context);
            }
        }

        return $description;
    }

    /**
     * The built-in extractors: naming strategies, PHPDoc, attributes, then the translation when a translator is
     * given.
     */
    public static function default(SummaryStrategy $summary, TagStrategy $tags, OperationIdStrategy $operationId, ?Translator $translator = null): self
    {
        return new self(
            new NamingOperationExtractor($summary, $tags, $operationId),
            new PhpDocOperationExtractor(),
            new AttributeOperationExtractor(),
            ...($translator === null ? [] : [new TranslationOperationExtractor($translator)]),
        );
    }
}
