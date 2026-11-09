<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Description;

use Ark4ne\OpenApi\Configuration\VariantsConfiguration;
use Ark4ne\OpenApi\Description\VariantBounds;
use Ark4ne\OpenApi\Tests\Fixtures\Variants\ApiRevision;
use Ark4ne\OpenApi\Tests\Fixtures\Variants\Channel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(VariantBounds::class)]
final class VariantBoundsTest extends TestCase
{
    public function test_a_backed_case_bounds_by_its_value(): void
    {
        $bounds = VariantBounds::between(ApiRevision::Initial, ApiRevision::Labels);

        self::assertSame(['2026-09-01', '2026-12-01'], [$bounds->since, $bounds->until]);
    }

    public function test_a_pure_case_bounds_by_its_name(): void
    {
        $bounds = VariantBounds::between(until: Channel::Beta);

        self::assertSame([null, 'Beta'], [$bounds->since, $bounds->until]);
    }

    public function test_a_string_bounds_by_itself(): void
    {
        $bounds = VariantBounds::between('b');

        self::assertSame(['b', null], [$bounds->since, $bounds->until]);
    }

    public function test_each_setter_replaces_its_bound(): void
    {
        $bounds = VariantBounds::between('a', 'c');
        $changed = $bounds->since(ApiRevision::Labels)->until(null);

        self::assertSame(['2026-12-01', null], [$changed->since, $changed->until]);
        self::assertSame(['a', 'c'], [$bounds->since, $bounds->until]);
    }

    /**
     * @return iterable<string, array{string|null, string|null, array<string, bool>}>
     */
    public static function coverages(): iterable
    {
        yield 'unbounded' => [null, null, ['a' => true, 'b' => true, 'c' => true, 'head' => true]];
        yield 'since' => ['b', null, ['a' => false, 'b' => true, 'c' => true, 'head' => true]];
        yield 'since and until' => ['a', 'b', ['a' => true, 'b' => true, 'c' => false, 'head' => false]];
        yield 'until only' => [null, 'b', ['a' => true, 'b' => true, 'c' => false, 'head' => false]];
        yield 'one variant' => ['c', 'c', ['a' => false, 'b' => false, 'c' => true, 'head' => false]];
        yield 'unknown since' => ['x', 'b', ['a' => true, 'b' => true, 'c' => false, 'head' => false]];
        yield 'unknown until' => ['b', 'x', ['a' => false, 'b' => true, 'c' => true, 'head' => true]];
    }

    /**
     * @param array<string, bool> $expected generation name → covered
     */
    #[DataProvider('coverages')]
    public function test_bounds_cover_the_variants_between_them_inclusive(?string $since, ?string $until, array $expected): void
    {
        $variants = self::variants();
        $bounds = VariantBounds::between($since, $until);
        $covered = [];

        foreach ($variants->names() as $name) {
            $covered[$name] = $bounds->covers($variants->named($name), $variants);
        }

        self::assertSame($expected, $covered);
    }

    public function test_the_bounds_naming_no_variant_are_unknown(): void
    {
        self::assertSame(['x', 'y'], VariantBounds::between('x', 'y')->unknown(self::variants()));
        self::assertSame(['y'], VariantBounds::between('a', 'y')->unknown(self::variants()));
        self::assertSame([], VariantBounds::between('a', 'c')->unknown(self::variants()));
        self::assertSame([], VariantBounds::between()->unknown(self::variants()));
    }

    public function test_bounds_since_a_variant_newer_than_their_until_are_inverted(): void
    {
        self::assertTrue(VariantBounds::between('c', 'a')->inverted(self::variants()));
        self::assertFalse(VariantBounds::between('b', 'b')->inverted(self::variants()));
        self::assertFalse(VariantBounds::between('a', 'c')->inverted(self::variants()));
        self::assertFalse(VariantBounds::between('c')->inverted(self::variants()));
        self::assertFalse(VariantBounds::between('x', 'a')->inverted(self::variants()));
    }

    public function test_the_name_of_the_head_is_no_variant(): void
    {
        self::assertSame(['head'], VariantBounds::between(until: 'head')->unknown(self::variants()));
    }

    private static function variants(): VariantsConfiguration
    {
        return VariantsConfiguration::fromArray('documents.v1.variants', ['values' => ['a', 'b', 'c'], 'head' => 'head']);
    }
}
