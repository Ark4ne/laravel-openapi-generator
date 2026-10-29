<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Sampling\Models;

use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Http\Resources\UsedResource;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Model;

/**
 * Names its resource with #[UseResource].
 */
#[UseResource(UsedResource::class)]
final class Tagged extends Model {}
