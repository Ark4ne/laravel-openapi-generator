<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Sampling;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApiWriter\Objects\Schema;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Describes a resource met in a sampled value, so that a nested resource is described as a returned one (E6).
 */
interface ResourceDescriber
{
    /**
     * The schema of the resource: a reference to its component, or the component itself when written inline.
     */
    public function describe(JsonResource $resource, GenerationContext $context): Schema;
}
