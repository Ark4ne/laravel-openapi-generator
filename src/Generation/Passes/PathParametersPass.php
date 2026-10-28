<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation\Passes;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Description\ParameterDescription;
use Ark4ne\OpenApi\Generation\Pass;
use Ark4ne\OpenApiWriter\Enums\ParameterLocation;

/**
 * The path parameters every operation of a path shares, moved up to the Path Item.
 */
final readonly class PathParametersPass implements Pass
{
    public function process(DocumentDescription $document, GenerationContext $context): DocumentDescription
    {
        /** @var array<string, list<OperationDescription>> $paths */
        $paths = [];

        foreach ($document->operations as $operation) {
            $paths[$operation->path][] = $operation;
        }

        $shared = [];

        foreach ($paths as $path => $operations) {
            $common = array_filter($operations[0]->request->parameters, static fn(ParameterDescription $parameter): bool => $parameter->in === ParameterLocation::Path);

            foreach ($operations as $operation) {
                $keys = array_map(self::key(...), $operation->request->parameters);
                $common = array_filter($common, static fn(ParameterDescription $parameter): bool => in_array(self::key($parameter), $keys, true));
            }

            if ($common !== []) {
                $shared[$path] = array_map(self::key(...), $common);
                $document = $document->pathParameters($path, ...array_values($common));
            }
        }

        return $document->operations(...array_map(static function (OperationDescription $operation) use ($shared): OperationDescription {
            $keys = $shared[$operation->path] ?? [];

            return $keys === [] ? $operation : $operation->request($operation->request->parameters(...array_values(array_filter($operation->request->parameters, static fn(ParameterDescription $parameter): bool => ! in_array(self::key($parameter), $keys, true)))));
        }, $document->operations));
    }

    private static function key(ParameterDescription $parameter): string
    {
        return implode('|', [$parameter->name, $parameter->in->value, $parameter->schema->toJson(), $parameter->required ? '1' : '0', $parameter->description ?? '']);
    }
}
