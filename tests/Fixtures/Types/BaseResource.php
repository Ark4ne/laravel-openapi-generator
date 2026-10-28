<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Types;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @template TModel
 * @template TExtra
 *
 * @extends JsonResource<TModel>
 */
abstract class BaseResource extends JsonResource {}
