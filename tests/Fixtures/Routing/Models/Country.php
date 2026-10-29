<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Routing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * String key (ISO code), not incrementing.
 */
final class Country extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';
}
