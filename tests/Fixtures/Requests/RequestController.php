<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Requests;

use Ark4ne\OpenApi\Attributes\Cookie;
use Ark4ne\OpenApi\Attributes\PathParameter;
use Ark4ne\OpenApi\Attributes\QueryParameter;
use Ark4ne\OpenApi\Attributes\RequestBody;
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

    #[RequestBody(resource: Post::class)]
    public function resource(Request $request): void {}

    public function plain(Request $request): void {}

    public function throwing(ThrowingRuleRequest $request): void {}

    public function describedPlain(DescribedPlainRequest $request): void {}

    #[QueryParameter('locale')]
    public function localized(SearchRequest $request): void {}

    #[RequestBody(description: 'The user')]
    public function documented(StoreUserRequest $request): void {}
}
