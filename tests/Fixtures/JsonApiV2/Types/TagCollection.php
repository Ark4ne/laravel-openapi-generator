<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiV2\Types;

use Ark4ne\JsonApi\Resources\JsonApiCollection;

/**
 * A JSON:API collection without `$collects`: Laravel's naming convention gives TagResource.
 */
final class TagCollection extends JsonApiCollection {}
