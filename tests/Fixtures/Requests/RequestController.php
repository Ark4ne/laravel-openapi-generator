<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Requests;

use Ark4ne\OpenApi\Attributes\Cookie;
use Ark4ne\OpenApi\Attributes\Header;
use Ark4ne\OpenApi\Attributes\PathParameter;
use Ark4ne\OpenApi\Attributes\QueryParameter;
use Ark4ne\OpenApi\Attributes\RequestBody;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\Http\Resources\AuthorResource;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Models\Post;
use Illuminate\Http\Request;

final class RequestController
{
    public function store(StoreUserRequest $request): void {}

    public function upload(UploadAvatarRequest $request): void {}

    public function search(SearchRequest $request): void {}

    public function update(UpdatePostRequest $request, Post $post): void {}

    public function failing(FailingRequest $request): void {}

    public function described(DescribedRequest $request): void {}

    #[QueryParameter('locale', type: 'string', description: 'Overridden by the action', deprecated: true)]
    #[Cookie('session', required: true)]
    public function attributed(AttributedRequest $request): void {}

    #[PathParameter('post', type: 'positive-int', description: 'Identifier of the post', example: 1)]
    #[RequestBody(type: 'array{title: string, tags?: list<string>}', description: 'The changes')]
    public function patch(Request $request, Post $post): void {}

    #[RequestBody(resource: AuthorResource::class)]
    public function resource(Request $request): void {}

    public function plain(Request $request): void {}

    public function throwing(ThrowingRuleRequest $request): void {}

    public function describedPlain(DescribedPlainRequest $request): void {}

    #[QueryParameter('locale')]
    public function localized(SearchRequest $request): void {}

    #[RequestBody(description: 'The user')]
    public function documented(StoreUserRequest $request): void {}

    #[QueryParameter('q', description: 'Words to search')]
    #[QueryParameter('page', example: 3)]
    public function completed(SearchRequest $request): void {}

    #[QueryParameter('q', type: 'non-empty-string', required: false)]
    public function optional(SearchRequest $request): void {}

    #[QueryParameter('q', type: 'non-empty-string', required: false, deprecated: true)]
    #[QueryParameter('page', description: 'Page of the results')]
    #[QueryParameter('page', type: 'int')]
    public function folded(SearchRequest $request): void {}

    #[QueryParameter('page', required: true)]
    #[QueryParameter('q', deprecated: true)]
    #[QueryParameter('q', description: 'Words to search', deprecated: false)]
    public function reordered(SearchRequest $request): void {}

    #[Header('x-tenant', description: 'Owner of the data')]
    public function tenant(DescribedRequest $request): void {}
}
