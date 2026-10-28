<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Routing\Models;

use Ark4ne\OpenApi\Tests\Fixtures\Routing\Enums\Status;
use Illuminate\Database\Eloquent\Model;

/**
 * Bound by its `slug`, with an integer `position` column.
 */
final class Post extends Model
{
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['position' => 'integer', 'rating' => 'float', 'featured' => 'boolean', 'status' => Status::class];
    }
}
