<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Types;

use Ark4ne\OpenApi\Tests\Fixtures\Routing\Models\Post;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @extends JsonResource<Post>
 */
final class PostResource extends JsonResource {}
