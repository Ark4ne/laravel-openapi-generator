<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Responses;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApi\Types\NamedType;
use Ark4ne\OpenApi\Types\Type;
use Ark4ne\OpenApiWriter\Enums\HttpMethod;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * A response without body: `Response` (204 for `DELETE`, 200 otherwise), `void`, `null`.
 */
final readonly class EmptyResponseExtractor implements ResponseExtractor
{
    public const PRIORITY = 100;

    public function priority(): int
    {
        return self::PRIORITY;
    }

    public function supports(Type $type, OperationContext $operation): bool
    {
        return $type instanceof NamedType && (in_array($type->name, ['void', 'null', 'never'], true) || in_array($type->name, [Response::class, SymfonyResponse::class], true));
    }

    public function extract(Type $type, OperationContext $operation, GenerationContext $context): array
    {
        if ($type instanceof NamedType && $type->name === 'never') {
            return [];
        }

        $deleted = $operation->method === HttpMethod::Delete && $type instanceof NamedType && $type->name !== 'void' && $type->name !== 'null';

        return [ResponseDescription::create($deleted ? 204 : 200)->description($deleted ? 'No Content' : 'OK')];
    }
}
