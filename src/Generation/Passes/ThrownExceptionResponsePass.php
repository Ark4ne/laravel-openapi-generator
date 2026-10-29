<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation\Passes;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Ark4ne\OpenApi\Description\ResponseOrigin;
use Ark4ne\OpenApi\Generation\Pass;
use Ark4ne\OpenApiWriter\Objects\Schema;

/**
 * Responses of one status (several exceptions, or an exception and an inferred response) as one: a declared response
 * wins; otherwise the descriptions are put together (the causes) and different bodies become `oneOf`.
 */
final readonly class ThrownExceptionResponsePass implements Pass
{
    public function process(DocumentDescription $document, GenerationContext $context): DocumentDescription
    {
        return $document->operations(...array_map(self::operation(...), $document->operations));
    }

    private static function operation(OperationDescription $operation): OperationDescription
    {
        /** @var array<int|string, non-empty-list<ResponseDescription>> $byStatus */
        $byStatus = [];

        foreach ($operation->responses as $response) {
            $byStatus[$response->status][] = $response;
        }

        return $operation->responses(...array_map(self::merge(...), array_values($byStatus)));
    }

    /**
     * @param non-empty-list<ResponseDescription> $responses
     */
    private static function merge(array $responses): ResponseDescription
    {
        foreach ($responses as $response) {
            if ($response->origin === ResponseOrigin::Declared) {
                return $response;
            }
        }

        $merged = $responses[0];

        if (count($responses) === 1) {
            return $merged;
        }

        $descriptions = array_values(array_unique(array_filter(array_map(static fn(ResponseDescription $response): ?string => $response->description, $responses), is_string(...))));
        $contents = [];

        foreach ($responses as $response) {
            foreach ($response->contents as $mediaType => $schema) {
                $contents[$mediaType][$schema->toJson()] = $schema;
            }
        }

        foreach ($contents as $mediaType => $schemas) {
            $schemas = array_values($schemas);
            $merged = $merged->content($mediaType, count($schemas) === 1 ? $schemas[0] : Schema::create()->oneOf(...$schemas));
        }

        return $merged->description(implode("\n", $descriptions));
    }
}
