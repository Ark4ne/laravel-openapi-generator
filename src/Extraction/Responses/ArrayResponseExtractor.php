<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Responses;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApi\Types\ArrayShapeType;
use Ark4ne\OpenApi\Types\GenericType;
use Ark4ne\OpenApi\Types\ListType;
use Ark4ne\OpenApi\Types\NamedType;
use Ark4ne\OpenApi\Types\Type;
use Illuminate\Contracts\Support\Arrayable;
use Traversable;

/**
 * Arrays, lists, shapes and collections returned as they are (Laravel writes them in JSON).
 */
final readonly class ArrayResponseExtractor implements ResponseExtractor
{
    public const PRIORITY = 190;

    public function __construct(
        private ValueSchemas $values,
    ) {}

    public function priority(): int
    {
        return self::PRIORITY;
    }

    public function supports(Type $type, OperationContext $operation): bool
    {
        return $type instanceof ListType
            || $type instanceof ArrayShapeType
            || ($type instanceof GenericType && (in_array($type->name, ['array', 'iterable'], true) || is_a($type->name, Traversable::class, true)))
            || ($type instanceof NamedType && (in_array($type->name, ['array', 'iterable'], true) || is_a($type->name, Arrayable::class, true)));
    }

    public function extract(Type $type, OperationContext $operation, GenerationContext $context): array
    {
        return [ResponseDescription::ok()->description('OK')->content('application/json', $this->values->of($type, $context))];
    }
}
