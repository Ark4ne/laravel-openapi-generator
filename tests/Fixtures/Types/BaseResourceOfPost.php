<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Types;

use Ark4ne\OpenApi\Tests\Fixtures\Routing\Models\Post;

/**
 * @template TUnused
 *
 * @extends BaseResource<Post, string>
 */
abstract class BaseResourceOfPost extends BaseResource {}
