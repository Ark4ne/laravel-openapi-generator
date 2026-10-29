<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Responses;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\HeaderDescription;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApi\Types\NamedType;
use Ark4ne\OpenApi\Types\Type;
use Ark4ne\OpenApiWriter\Objects\Schema;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * A redirection: 302 with its `Location`.
 */
final readonly class RedirectResponseExtractor implements ResponseExtractor
{
    public const PRIORITY = 240;

    public function priority(): int
    {
        return self::PRIORITY;
    }

    public function supports(Type $type, OperationContext $operation): bool
    {
        return $type instanceof NamedType && is_a($type->name, RedirectResponse::class, true);
    }

    public function extract(Type $type, OperationContext $operation, GenerationContext $context): array
    {
        return [ResponseDescription::create(302)->description('Found')->headers(HeaderDescription::create('Location', Schema::string()->format('uri'))->required())];
    }
}
