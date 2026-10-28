<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Responses;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApi\Sampling\ResourceModels;
use Ark4ne\OpenApi\Sampling\Samplers;
use Ark4ne\OpenApi\Sampling\SchemaInferrer;
use Ark4ne\OpenApi\Types\Type;

final class ResponseExtractors
{
    /** @var list<ResponseExtractor> */
    private readonly array $extractors;

    public function __construct(ResponseExtractor ...$extractors)
    {
        $extractors = array_values($extractors);
        usort($extractors, static fn(ResponseExtractor $a, ResponseExtractor $b): int => $b->priority() <=> $a->priority());
        $this->extractors = $extractors;
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
        foreach ($this->extractors as $extractor) {
            if ($extractor->supports($type, $operation)) {
                return $extractor->extract($type, $operation, $context);
            }
        }

        $context->diagnostics->info(Stage::Response, "no response extractor for {$type->toString()}", $operation->route->label());

        return [];
    }

    /**
     * The built-in extractors.
     */
    public static function default(ResourceSchemas $resources, ResourceModels $models, ValueSchemas $values, Samplers $samplers, SchemaInferrer $inferrer): self
    {
        return new self(
            new ResourceCollectionExtractor($resources, $models),
            new JsonResourceExtractor($resources),
            new PaginatorExtractor($values),
            new ResponsableExtractor($samplers, $inferrer),
            new RedirectResponseExtractor(),
            new FileResponseExtractor(),
            new StreamedResponseExtractor(),
            new JsonResponseExtractor(),
            new ViewResponseExtractor(),
            new ModelResponseExtractor($values),
            new ArrayResponseExtractor($values),
            new ScalarResponseExtractor(),
            new EmptyResponseExtractor(),
        );
    }
}
