<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Extensions;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApi\Extraction\Responses\ResponseExtractor;
use Ark4ne\OpenApi\Types\NamedType;
use Ark4ne\OpenApi\Types\Type;

/**
 * A Teapot returned answers 418.
 */
final readonly class TeapotResponseExtractor implements ResponseExtractor
{
    public function priority(): int
    {
        return 1000;
    }

    public function supports(Type $type, OperationContext $operation): bool
    {
        return $type instanceof NamedType && $type->name === Teapot::class;
    }

    public function extract(Type $type, OperationContext $operation, GenerationContext $context): array
    {
        return [ResponseDescription::create(418)->description("I'm a teapot")];
    }
}
