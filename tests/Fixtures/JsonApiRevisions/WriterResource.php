<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiRevisions;

use Ark4ne\JsonApi\Definition\JsonApiUntil;
use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Models\User;
use Illuminate\Http\Request;

/**
 * The writers, up to Initial: the members replace them.
 *
 * @extends JsonApiResource<User>
 */
#[JsonApiType('writers')]
#[JsonApiUntil(ApiRevision::Initial)]
final class WriterResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'email' => $this->string(),
        ];
    }
}
