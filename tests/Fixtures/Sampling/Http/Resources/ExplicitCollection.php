<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Sampling\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

final class ExplicitCollection extends ResourceCollection
{
    public $collects = MixinResource::class;
}
