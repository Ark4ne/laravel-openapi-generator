<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\Types;

use Ark4ne\ApiRevisions\Schema\Schema;
use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;

/**
 * Nested resources: one that nests itself, one that cannot be compiled.
 *
 * @extends JsonApiResource<\Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Models\Post>
 */
#[JsonApiType('nestings')]
final class NestingResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'node' => Schema::resource(NodeResource::class),
            'broken' => Schema::resource(BrokenNestedResource::class),
        ];
    }
}
