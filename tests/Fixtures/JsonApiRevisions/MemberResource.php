<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiRevisions;

use Ark4ne\JsonApi\Definition\JsonApiSince;
use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Models\User;
use Illuminate\Http\Request;

/**
 * The members, from Labels: they replace the writers.
 *
 * @extends JsonApiResource<User>
 */
#[JsonApiType('members')]
#[JsonApiSince(ApiRevision::Labels)]
final class MemberResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'name' => $this->string(),
        ];
    }
}
