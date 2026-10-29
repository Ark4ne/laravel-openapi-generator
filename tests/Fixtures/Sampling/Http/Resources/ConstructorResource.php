<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Sampling\Http\Resources;

use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Models\Author;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Names its model by the type of its constructor.
 */
final class ConstructorResource extends JsonResource
{
    public function __construct(Author $resource)
    {
        parent::__construct($resource);
    }
}
