<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Responses;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApi\Types\LiteralType;
use Ark4ne\OpenApi\Types\NamedType;
use Ark4ne\OpenApi\Types\Type;
use Ark4ne\OpenApiWriter\Objects\Schema;

/**
 * A string or a number returned as it is: an HTML body, as Laravel sends it.
 */
final readonly class ScalarResponseExtractor implements ResponseExtractor
{
    public const PRIORITY = 180;

    public function priority(): int
    {
        return self::PRIORITY;
    }

    public function supports(Type $type, OperationContext $operation): bool
    {
        return ($type instanceof NamedType && in_array($type->name, ['string', 'int', 'float', 'bool', 'non-empty-string', 'numeric'], true)) || $type instanceof LiteralType;
    }

    public function extract(Type $type, OperationContext $operation, GenerationContext $context): array
    {
        return [ResponseDescription::ok()->description('OK')->content('text/html', Schema::string())];
    }
}
