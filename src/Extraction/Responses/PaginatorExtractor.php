<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Responses;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApi\Types\GenericType;
use Ark4ne\OpenApi\Types\NamedType;
use Ark4ne\OpenApi\Types\Type;
use Ark4ne\OpenApiWriter\Objects\Schema;

/**
 * A paginator returned as it is (`LengthAwarePaginator<int, User>`, or its contract): the shape of its `toArray()`.
 */
final readonly class PaginatorExtractor implements ResponseExtractor
{
    public const PRIORITY = 260;

    public function __construct(
        private ValueSchemas $values,
    ) {}

    public function priority(): int
    {
        return self::PRIORITY;
    }

    public function supports(Type $type, OperationContext $operation): bool
    {
        return ($type instanceof GenericType || $type instanceof NamedType) && Paginations::supports($type->name);
    }

    public function extract(Type $type, OperationContext $operation, GenerationContext $context): array
    {
        if (! $type instanceof GenericType && ! $type instanceof NamedType) {
            return [];
        }

        // Without type arguments, the items have no known shape.
        $items = $type instanceof GenericType ? $this->values->of($type->arguments[array_key_last($type->arguments)], $context) : Schema::create();

        return [ResponseDescription::ok()->description('OK')->content('application/json', Paginations::raw($type->name, $items))];
    }
}
