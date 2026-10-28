<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Requests;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\RequestDescription;
use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApi\Extraction\Rules\FieldsBuilder;
use Ark4ne\OpenApi\Extraction\Rules\RuleMappers;
use Ark4ne\OpenApi\Extraction\Rules\RuleParser;
use Ark4ne\OpenApi\Extraction\Strategies\QueryStrategy;
use Ark4ne\OpenApi\Types\TypeResolver;
use Illuminate\Contracts\Container\Container;

final class RequestExtractors
{
    /** @var list<RequestExtractor> */
    private readonly array $extractors;

    public function __construct(RequestExtractor ...$extractors)
    {
        $extractors = array_values($extractors);
        usort($extractors, static fn(RequestExtractor $a, RequestExtractor $b): int => $a->priority() <=> $b->priority());
        $this->extractors = $extractors;
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
        foreach ($this->extractors as $extractor) {
            if ($extractor->supports($operation)) {
                $request = $extractor->extract($operation, $request, $context);
            }
        }

        return $request;
    }

    /**
     * The built-in extractors: path parameters, form request, manual description, attributes.
     */
    public static function default(Container $container, QueryStrategy $query, ?RuleMappers $mappers = null, TypeResolver $types = new TypeResolver()): self
    {
        $requests = new RequestFactory($container);
        $parser = new RuleParser();
        $fields = new FieldsBuilder($mappers ?? RuleMappers::default());
        $placer = new FieldPlacer($query);

        return new self(
            new PathParameterRequestExtractor(),
            new FormRequestExtractor($requests, $parser, $fields, $placer),
            new DescribableRequestExtractor($requests, $parser, $fields, $placer),
            new AttributeRequestExtractor($types),
        );
    }
}
