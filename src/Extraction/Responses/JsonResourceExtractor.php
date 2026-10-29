<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Responses;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApi\Types\NamedType;
use Ark4ne\OpenApi\Types\Type;
use Ark4ne\OpenApiWriter\Enums\HttpMethod;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A `JsonResource`: its component under `data` (or unwrapped), with `with()` and `additional()`, or as its format
 * writes it; 201 when
 * returned by `POST` (Laravel answers 201 for a model just created), 200 otherwise.
 */
final readonly class JsonResourceExtractor implements ResponseExtractor
{
    public const PRIORITY = 300;

    public function __construct(
        private ResourceSchemas $resources,
    ) {}

    public function priority(): int
    {
        return self::PRIORITY;
    }

    public function supports(Type $type, OperationContext $operation): bool
    {
        return $type instanceof NamedType && is_subclass_of($type->name, JsonResource::class) && ! is_subclass_of($type->name, ResourceCollection::class);
    }

    public function extract(Type $type, OperationContext $operation, GenerationContext $context): array
    {
        if (! $type instanceof NamedType || ! is_subclass_of($type->name, JsonResource::class)) {
            return [];
        }

        $created = $operation->method === HttpMethod::Post;
        $body = $this->resources->envelope($type->name, $this->resources->schema($type->name, $context), $context);

        return [ResponseDescription::create($created ? 201 : 200)->description($created ? 'Created' : 'OK')->content($this->resources->mediaType($type->name), $body)];
    }
}
