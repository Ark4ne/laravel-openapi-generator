<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiRevisions;

use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Models\Post;
use Illuminate\Http\Request;

/**
 * An article across the revisions: `name` becomes `label` in Labels, `summary` exists from Labels, `status` up to
 * Initial; its `writer` (a writer) becomes its `author` (a member) in Labels.
 *
 * @extends JsonApiResource<Post>
 */
#[JsonApiType('articles')]
final class ArticleResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'label' => $this->string('title')->was(ApiRevision::Initial, name: 'name'),
            'summary' => $this->string('content')->since(ApiRevision::Labels),
            'status' => $this->string()->until(ApiRevision::Initial),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'author' => $this->one(MemberResource::class, 'user')->was(ApiRevision::Initial, name: 'writer', value: $this->one(WriterResource::class, 'user')),
        ];
    }
}
