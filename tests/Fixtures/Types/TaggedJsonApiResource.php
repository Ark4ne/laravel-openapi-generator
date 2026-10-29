<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Types;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;

/**
 * A JSON:API resource related to a collection class, and to many resources; the meta of both count them.
 */
final class TaggedJsonApiResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return ['name' => $this->string()];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'tags' => $this->one(TagCollection::class)->meta(static fn(TagCollection $tags): array => ['total' => $tags->count()]),
            'labels' => $this->many(TagResource::class)->meta(static fn(TagCollection|\Illuminate\Http\Resources\Json\ResourceCollection $labels): array => ['count' => $labels->count()]),
        ];
    }
}
