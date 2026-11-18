<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3;

use Ark4ne\ApiRevisions\Schema\Schema;
use Ark4ne\JsonApi\Descriptors\Schemas\Conditions;
use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Models\Post;
use Illuminate\Http\Request;

/**
 * An article across the revisions: `name` becomes `label` in Labels, `summary` exists from Labels, `status` up to
 * Initial; its `writer` (a writer) becomes its `author` (a member) in Labels. `notes` is rendered only when the sparse
 * fieldset names it; `place` has a `town` up to Initial, a `city` after.
 *
 * @extends JsonApiResource<Post>
 */
#[JsonApiType('articles')]
final class ArticleResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'label' => Schema::string('title')->max(120)->describe('The title.')->was(ApiRevision::Initial, name: 'name'),
            'summary' => Schema::string('content')->nullable()->since(ApiRevision::Labels),
            'status' => Schema::enum(Stage::class, static fn(): Stage => Stage::Draft)->until(ApiRevision::Initial),
            'notes' => Conditions::whenInFields(Schema::string('content')),
            'place' => Schema::object(['city' => Schema::string()], static fn(): array => ['city' => 'Lyon'])
                ->was(ApiRevision::Initial, schema: Schema::object(['town' => Schema::string()->nullable()])),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'author' => $this->one(MemberResource::class, 'user')->was(ApiRevision::Initial, name: 'writer', schema: $this->one(WriterResource::class, 'user')),
        ];
    }

    protected function toResourceMeta(Request $request): iterable
    {
        return [
            'created_at' => Schema::dateTime()->nullable(),
        ];
    }

    protected function toDocumentMeta(Request $request): iterable
    {
        return [
            'catalog' => Schema::string(static fn(): string => 'articles'),
        ];
    }

    protected function toLinks(Request $request): iterable
    {
        return ['self' => static fn(): string => 'https://api.example.com/articles'];
    }
}
