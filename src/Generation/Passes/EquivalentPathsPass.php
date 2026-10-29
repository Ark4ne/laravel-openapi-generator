<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation\Passes;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Description\ParameterDescription;
use Ark4ne\OpenApi\Generation\Pass;
use Ark4ne\OpenApiWriter\Enums\ParameterLocation;

/**
 * Paths that differ only by the names of their parameters (`/users/{id}`, `/users/{user}`) are one path for the
 * router, and OpenAPI forbids them side by side: the operations of the later ones join the first path, their path
 * parameters renamed in order, with an info.
 */
final readonly class EquivalentPathsPass implements Pass
{
    private const PARAMETER = '/\{([^}]+)}/';

    public function process(DocumentDescription $document, GenerationContext $context): DocumentDescription
    {
        /** @var array<string, string> $first path with anonymous parameters → first path seen */
        $first = [];
        $operations = [];

        foreach ($document->operations as $operation) {
            $key = (string) preg_replace(self::PARAMETER, '{}', $operation->path);
            $path = $first[$key] ??= $operation->path;

            if ($path !== $operation->path) {
                $context->diagnostics->info(Stage::Pass, "{$operation->path} is the path {$path} for the router, written as it", $operation->route);
                $operation = self::rename($operation, $path);
            }

            $operations[] = $operation;
        }

        return $document->operations(...$operations);
    }

    private static function rename(OperationDescription $operation, string $path): OperationDescription
    {
        preg_match_all(self::PARAMETER, $operation->path, $from);
        preg_match_all(self::PARAMETER, $path, $to);
        $names = array_combine($from[1], $to[1]);
        $parameters = array_map(
            static fn(ParameterDescription $parameter): ParameterDescription => $parameter->in === ParameterLocation::Path && isset($names[$parameter->name]) ? $parameter->name($names[$parameter->name]) : $parameter,
            $operation->request->parameters,
        );

        return $operation->path($path)->request($operation->request->parameters(...$parameters));
    }
}
