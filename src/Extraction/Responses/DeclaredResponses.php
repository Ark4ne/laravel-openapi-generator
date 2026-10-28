<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Responses;

use Ark4ne\OpenApi\Attributes\Response;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\HeaderDescription;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Ark4ne\OpenApi\Description\ResponseOrigin;
use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApi\Sampling\TypeSchemas;
use Ark4ne\OpenApi\Types\TypeResolver;
use Ark4ne\OpenApiWriter\Objects\Schema;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use ReflectionClass;
use ReflectionFunctionAbstract;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * The `#[Response]` of the action, each read in the scope of the declaration that carries it.
 *
 * The body is the resource (paginated with `paginator`) or the PHPDoc type; `text/event-stream` makes the type the
 * schema of one event. A response declared without body keeps the content the generator inferred for its status.
 */
final readonly class DeclaredResponses
{
    public function __construct(
        private ResourceSchemas $resources,
        private ResourceCollectionExtractor $collections,
        private TypeResolver $types,
    ) {}

    /**
     * Each response with the declaration that carries it, from the parent declaration down.
     *
     * @return list<array{ResponseDescription, ReflectionFunctionAbstract}>
     */
    public function describe(OperationContext $operation, GenerationContext $context): array
    {
        $responses = [];

        foreach ($operation->declaredActionAttributes(Response::class, $context) as [$attribute, $declaration]) {
            $responses[] = [$this->response($attribute, $declaration, $context), $declaration];
        }

        return $responses;
    }

    /**
     * The description of a status: its HTTP phrase.
     *
     * @param int|'default' $status
     */
    public static function phrase(int|string $status): ?string
    {
        return is_int($status) ? HttpResponse::$statusTexts[$status] ?? null : 'Default';
    }

    /**
     * The response of an attribute, on an action or on an exception class.
     *
     * @param ReflectionFunctionAbstract|ReflectionClass<covariant object> $scope
     */
    public function response(Response $attribute, ReflectionFunctionAbstract|ReflectionClass $scope, GenerationContext $context): ResponseDescription
    {
        $response = ResponseDescription::create($attribute->status)
            ->description($attribute->description ?? self::phrase($attribute->status))
            ->summary($attribute->summary)
            ->origin(ResponseOrigin::Declared);
        $body = $this->body($attribute, $scope, $context);

        if ($body !== null) {
            $body = $attribute->example === null ? $body : $body->example($attribute->example);
            $mediaType = $attribute->mediaType ?? 'application/json';
            $response = $mediaType === 'text/event-stream' ? $response->itemContent($mediaType, $body) : $response->content($mediaType, $body);
        }

        foreach ($attribute->headers as $name => $type) {
            $response = $response->addHeaders(HeaderDescription::create($name, TypeSchemas::of($this->types->parse($type, $scope))));
        }

        return $response;
    }

    /**
     * @param ReflectionFunctionAbstract|ReflectionClass<covariant object> $scope
     */
    private function body(Response $attribute, ReflectionFunctionAbstract|ReflectionClass $scope, GenerationContext $context): ?Schema
    {
        $resource = $attribute->resource;

        return match (true) {
            $resource !== null && $attribute->paginator !== null && is_subclass_of($resource, JsonResource::class) => $this->collections->paginated($resource, $attribute->paginator, $context),
            $resource !== null && is_subclass_of($resource, ResourceCollection::class) => $this->collections->collection($resource, $context),
            $resource !== null && is_subclass_of($resource, JsonResource::class) => $this->resources->envelope($resource, $this->resources->schema($resource, $context), $context),
            $attribute->type !== null => TypeSchemas::of($this->types->parse($attribute->type, $scope)),
            default => null,
        };
    }
}
