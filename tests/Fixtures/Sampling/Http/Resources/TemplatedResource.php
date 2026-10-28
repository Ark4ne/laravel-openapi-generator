<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Sampling\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A base resource with its own template, not bound to the one of JsonResource (as JsonApiResource).
 *
 * @template T
 */
abstract class TemplatedResource extends JsonResource {}
