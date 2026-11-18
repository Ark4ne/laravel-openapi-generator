<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\Types;

use Ark4ne\ApiRevisions\Schema\Schema;
use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use DateTimeInterface;
use Illuminate\Http\Request;

/**
 * The patterns of the base: a PCRE JSON Schema can or cannot carry, the PHP date formats JSON Schema names or not.
 *
 * @extends JsonApiResource<\Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Models\Post>
 */
#[JsonApiType('patterned')]
final class PatternedResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'slug' => Schema::string()->pattern('/^[a-z]+$/u'),
            'word' => Schema::string()->pattern('/^abc$/i'),
            'day' => Schema::date(),
            'iso_day' => Schema::date()->format('Y-m-d'),
            'local_day' => Schema::date()->format('d/m/Y'),
            'spoken_day' => Schema::date()->format('D, d M Y'),
            'at' => Schema::dateTime(),
            'at_extended' => Schema::dateTime()->format(DateTimeInterface::RFC3339_EXTENDED),
            'at_sql' => Schema::dateTime()->format('Y-m-d H:i:s'),
            'described_day' => Schema::date()->format('d/m/Y')->describe('The day.'),
        ];
    }
}
