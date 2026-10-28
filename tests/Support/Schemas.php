<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Support;

use Ark4ne\OpenApiWriter\Objects\Schema;
use PHPUnit\Framework\Assert;

/**
 * Compares schemas whatever the order of their keys, at every level: the writer follows the order of the
 * specification, not the order of the calls. The order of lists (`enum`, `required`, `anyOf`) is kept.
 */
final class Schemas
{
    /**
     * @param array<array-key, mixed>|string $expected an array, or its JSON
     */
    public static function assertSame(array|string $expected, Schema $schema, string $message = ''): void
    {
        $expected = is_string($expected) ? json_decode($expected, true, flags: JSON_THROW_ON_ERROR) : $expected;

        Assert::assertSame(self::normalize($expected), self::normalize(json_decode($schema->toJson(), true, flags: JSON_THROW_ON_ERROR)), $message);
    }

    private static function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $value = array_map(self::normalize(...), $value);

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
