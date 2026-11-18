<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3;

use Ark4ne\ApiRevisions\Schema\Schema;
use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Models\Post;
use Illuminate\Http\Request;

/**
 * Every kind of value a shape gives, its constraints and its documentation.
 *
 * @extends JsonApiResource<Post>
 */
#[JsonApiType('showcases')]
final class ShowcaseResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'title' => Schema::string()->min(1)->max(80)->pattern('/^[A-Z]/')->describe('The title.')->example('Hello')->deprecated(),
            'code' => $this->uuid(static fn(): string => '0190f6a0-0000-7000-8000-000000000000'),
            'rank' => Schema::int(static fn(): int => 1)->min(1)->max(5),
            'ratio' => Schema::float(static fn(): float => 0.5)->nullable(),
            'active' => Schema::bool(static fn(): bool => true),
            'day' => Schema::date(static fn(): string => '2026-01-01'),
            'opened' => Schema::dateTime(static fn(): string => '2026-01-01T10:00:00+00:00'),
            'clock' => $this->time(static fn(): string => '10:00:00'),
            'stage' => Schema::enum(Stage::class, static fn(): Stage => Stage::Draft)->nullable(),
            'ranks' => Schema::list(Schema::int()->nullable(), static fn(): array => [1, null])->min(1)->max(3),
            'tags' => Schema::list(Schema::string(), static fn(): array => []),
            'profile' => Schema::object([
                'city' => Schema::string()->nullable(),
                'zip' => Schema::int()->min(0),
            ], static fn(): array => ['city' => 'Lyon', 'zip' => 69000]),
            'price' => $this->decimal(static fn(): string => '1.50'),
            'anything' => $this->mixed(static fn(): array => ['a' => 1]),
            'tag' => Schema::resource(TagResource::class, static fn(): array => ['name' => 'php']),
        ];
    }
}
