<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Sampling\Http\Resources;

use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Models\Author;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Author
 */
final class MixinResource extends JsonResource {}
