<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Responses;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApiWriter\Objects\Schema;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * How a family of resources is written (JSON:API): its component, its document and its collections, in its own
 * media type. ResourceSchemas asks the first format that supports a resource, wherever the resource is met: returned,
 * declared (`#[Response(resource:)]`, `#[RequestBody(resource:)]`, `#[Callback]`) or nested; the samples and the
 * components stay its own.
 */
interface ResourceFormat
{
    /**
     * @param class-string<JsonResource> $resource
     */
    public function supports(string $resource): bool;

    public function mediaType(): string;

    /**
     * The schema of the resource, registered as its component.
     *
     * @param class-string<JsonResource> $resource
     */
    public function component(string $resource, ResourceSchemas $resources, GenerationContext $context): Schema;

    /**
     * The body of one resource returned.
     *
     * @param class-string<JsonResource> $resource
     * @param Schema $data the schema of the resource (a reference to its component)
     */
    public function document(string $resource, Schema $data, ResourceSchemas $resources, GenerationContext $context): Schema;

    /**
     * The body of a request that sends the resource (`#[RequestBody(resource:)]`).
     *
     * @param class-string<JsonResource> $resource
     * @param Schema $data the schema of the resource (a reference to its component)
     */
    public function request(string $resource, Schema $data, ResourceSchemas $resources, GenerationContext $context): Schema;

    /**
     * The body of a list of the resource, paginated when a paginator is given.
     *
     * @param class-string<JsonResource> $resource
     * @param Schema $data the schema of one resource (a reference to its component)
     */
    public function collection(string $resource, Schema $data, ?string $paginator, ResourceSchemas $resources, GenerationContext $context): Schema;
}
