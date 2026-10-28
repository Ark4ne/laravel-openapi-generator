<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Responses;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApi\Types\NamedType;
use Ark4ne\OpenApi\Types\Type;
use Illuminate\Contracts\View\View;

/**
 * Views and Inertia pages: not an API response, not documented.
 */
final readonly class ViewResponseExtractor implements ResponseExtractor
{
    public const PRIORITY = 210;

    public function priority(): int
    {
        return self::PRIORITY;
    }

    public function supports(Type $type, OperationContext $operation): bool
    {
        return $type instanceof NamedType && (is_a($type->name, View::class, true) || $type->name === 'Inertia\\Response');
    }

    public function extract(Type $type, OperationContext $operation, GenerationContext $context): array
    {
        $context->diagnostics->info(Stage::Response, 'views are not documented', $operation->route->label());

        return [];
    }
}
