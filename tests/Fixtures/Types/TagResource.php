<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Types;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;

/**
 * The resource TagCollection collects by the naming convention.
 */
final class TagResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'tag';
    }

    public function toAttributes(Request $request): iterable
    {
        return ['label' => $this->string()];
    }
}
