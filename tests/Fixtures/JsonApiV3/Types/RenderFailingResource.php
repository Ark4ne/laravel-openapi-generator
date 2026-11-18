<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\Types;

use Ark4ne\ApiRevisions\Schema\Schema;
use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A value typed by the sample, whose rendering fails.
 *
 * @extends JsonApiResource<\Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Models\Post>
 */
#[JsonApiType('failing')]
final class RenderFailingResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'title' => Schema::string(),
            'boom' => $this->mixed(static fn(): never => throw new RuntimeException('exploded')),
        ];
    }
}
