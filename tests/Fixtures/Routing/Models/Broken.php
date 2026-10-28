<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Routing\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * A model that cannot be instantiated.
 */
final class Broken extends Model
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        throw new RuntimeException('not instantiable');
    }
}
