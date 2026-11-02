<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiV2\Types;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;

/**
 * A JSON:API resource related to a collection class, and to many resources with meta on the relationship and on
 * each identifier.
 */
final class TaggedResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return ['name' => $this->string()];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'tags' => $this->many(TagCollection::class)->links(static fn(): array => ['self' => 'https://api.example.com/tags']),
            'labels' => $this->many(TagResource::class)
                ->meta(static fn(): array => ['count' => 0])
                ->identifierMeta(static fn(): array => ['weight' => 1]),
        ];
    }
}
