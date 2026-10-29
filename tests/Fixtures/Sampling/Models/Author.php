<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Sampling\Models;

use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A model without factory.
 *
 * @property int $id
 * @property string $name
 * @property Carbon|null $born_at
 * @property bool $active
 * @property float $score
 */
final class Author extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['born_at' => 'immutable_date', 'tags' => AsCollection::class, 'score' => 'float'];
    }
}
