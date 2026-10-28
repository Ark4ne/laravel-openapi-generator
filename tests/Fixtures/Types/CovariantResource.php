<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Types;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Templates declared as Laravel's paginators declare theirs.
 *
 * @template TKey
 * @template-covariant TValue
 *
 * @extends JsonResource<TValue>
 */
abstract class CovariantResource extends JsonResource {}
