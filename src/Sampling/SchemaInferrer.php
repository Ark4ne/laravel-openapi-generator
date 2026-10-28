<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Sampling;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Types\Type;
use Ark4ne\OpenApiWriter\Objects\Schema;

/**
 * Infers a schema from a sampled value, completed by its declared type when there is one.
 */
final class SchemaInferrer
{
    public function infer(mixed $value, GenerationContext $context, ?Type $declared = null): Schema
    {
        return Schema::create();
    }
}
