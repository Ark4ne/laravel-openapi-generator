<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Responses;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApi\Types\NamedType;
use Ark4ne\OpenApi\Types\Type;
use Illuminate\Database\Eloquent\Model;

/**
 * A model returned as it is: its sampled attributes.
 */
final readonly class ModelResponseExtractor implements ResponseExtractor
{
    public const PRIORITY = 200;

    public function __construct(
        private ValueSchemas $values,
    ) {}

    public function priority(): int
    {
        return self::PRIORITY;
    }

    public function supports(Type $type, OperationContext $operation): bool
    {
        return $type instanceof NamedType && is_subclass_of($type->name, Model::class);
    }

    public function extract(Type $type, OperationContext $operation, GenerationContext $context): array
    {
        return [ResponseDescription::ok()->description('OK')->content('application/json', $this->values->of($type, $context))];
    }
}
