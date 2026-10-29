<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Responses;

use Ark4ne\OpenApi\Attributes\Response as ResponseAttribute;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApi\Types\NamedType;
use Ark4ne\OpenApi\Types\Type;
use Ark4ne\OpenApiWriter\Objects\Schema;
use Illuminate\Http\JsonResponse;

/**
 * A `JsonResponse`: its body is known only from `#[Response]`; without one, a free object and an info.
 */
final readonly class JsonResponseExtractor implements ResponseExtractor
{
    public const PRIORITY = 220;

    public function priority(): int
    {
        return self::PRIORITY;
    }

    public function supports(Type $type, OperationContext $operation): bool
    {
        return $type instanceof NamedType && is_a($type->name, JsonResponse::class, true);
    }

    public function extract(Type $type, OperationContext $operation, GenerationContext $context): array
    {
        $declared = array_filter($operation->actionAttributes(ResponseAttribute::class, $context), static fn(ResponseAttribute $response): bool => $response->resource !== null || $response->type !== null);

        if ($declared === []) {
            $context->diagnostics->info(Stage::Response, 'JsonResponse without #[Response], body described as a free object', $operation->route->label());
        }

        return [ResponseDescription::ok()->description('OK')->content('application/json', Schema::object())];
    }
}
