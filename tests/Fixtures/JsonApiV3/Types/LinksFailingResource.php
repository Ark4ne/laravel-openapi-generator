<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\Types;

use Ark4ne\ApiRevisions\Schema\Schema;
use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A declaration compiled with a finding of the package: links that cannot be read without model (`hook-failure`).
 *
 * @extends JsonApiResource<\Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Models\Post>
 */
#[JsonApiType('links-failing')]
final class LinksFailingResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return ['title' => Schema::string()];
    }

    protected function toLinks(Request $request): iterable
    {
        throw new RuntimeException('no model');
    }
}
