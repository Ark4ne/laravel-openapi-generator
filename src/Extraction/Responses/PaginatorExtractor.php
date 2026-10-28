<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Responses;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApi\Types\GenericType;
use Ark4ne\OpenApi\Types\Type;

/**
 * A paginator returned as it is (`LengthAwarePaginator<int, User>`): the shape of its `toArray()`.
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
        return $type instanceof GenericType && Paginations::supports($type->name);
    }

    public function extract(Type $type, OperationContext $operation, GenerationContext $context): array
    {
        if (! $type instanceof GenericType) {
            return [];
        }

        $items = $this->values->of($type->arguments[array_key_last($type->arguments)], $context);

        return [ResponseDescription::ok()->description('OK')->content('application/json', Paginations::raw($type->name, $items))];
    }
}
