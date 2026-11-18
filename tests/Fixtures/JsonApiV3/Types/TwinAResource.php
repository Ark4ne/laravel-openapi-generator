<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\Types;

use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;

/**
 * @extends JsonApiResource<\Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Models\Post>
 */
#[JsonApiType('twin')]
final class TwinAResource extends JsonApiResource {}
