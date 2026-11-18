<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\Types;

use Ark4ne\JsonApi\Resources\JsonApiCollection;

/**
 * @extends JsonApiCollection<PostResource>
 */
final class PostCollection extends JsonApiCollection
{
    public $collects = PostResource::class;
}
