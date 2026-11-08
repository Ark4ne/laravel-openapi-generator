<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Configuration;

use Ark4ne\OpenApi\Configuration\Variant;
use Ark4ne\OpenApi\Tests\Fixtures\Variants\ApiRevision;
use Ark4ne\OpenApi\Tests\Fixtures\Variants\Channel;
use Ark4ne\OpenApi\Tests\TestCase;
use Closure;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Variant::class)]
final class VariantTest extends TestCase
{
    public function test_a_variant_of_a_backed_case_is_named_by_its_value(): void
    {
        $variant = Variant::ofCase(ApiRevision::Labels, 1);

        self::assertSame(ApiRevision::Labels, $variant->value);
        self::assertSame('2026-12-01', $variant->name);
        self::assertSame(1, $variant->position);
        self::assertSame(ApiRevision::class, $variant->dimension);
    }

    public function test_a_variant_of_a_pure_case_is_named_by_its_name(): void
    {
        $variant = Variant::ofCase(Channel::Beta, 1);

        self::assertSame('Beta', $variant->name);
        self::assertSame(Channel::class, $variant->dimension);
    }

    public function test_a_variant_of_a_string_is_named_by_the_string(): void
    {
        $variant = Variant::ofString('v2', 1, 'documents.v1.variants.values');

        self::assertSame('v2', $variant->value);
        self::assertSame('v2', $variant->name);
        self::assertSame('documents.v1.variants.values', $variant->dimension);
    }

    public function test_variants_are_compared_by_their_declaration_order(): void
    {
        $initial = Variant::ofCase(ApiRevision::Initial, 0);
        $labels = Variant::ofCase(ApiRevision::Labels, 1);

        self::assertTrue($initial->isAtMost($labels));
        self::assertFalse($labels->isAtMost($initial));
        self::assertTrue($labels->isAtLeast($initial));
        self::assertFalse($initial->isAtLeast($labels));
    }

    public function test_the_bounds_of_a_comparison_are_inclusive(): void
    {
        $labels = Variant::ofCase(ApiRevision::Labels, 1);

        self::assertTrue($labels->isAtMost(Variant::ofCase(ApiRevision::Labels, 1)));
        self::assertTrue($labels->isAtLeast(Variant::ofCase(ApiRevision::Labels, 1)));
    }

    public function test_a_variant_is_the_variant_of_the_same_position(): void
    {
        $labels = Variant::ofCase(ApiRevision::Labels, 1);

        self::assertTrue($labels->is(Variant::ofCase(ApiRevision::Labels, 1)));
        self::assertFalse($labels->is(Variant::ofCase(ApiRevision::Initial, 0)));
    }

    /**
     * @param Closure(Variant, Variant): bool $compare
     */
    #[DataProvider('comparisons')]
    public function test_comparing_variants_of_two_dimensions_throws(Closure $compare): void
    {
        $this->expectException(LogicException::class);

        self::assertFalse($compare(Variant::ofCase(ApiRevision::Initial, 0), Variant::ofCase(Channel::Alpha, 0)));
    }

    /**
     * @return iterable<string, array{Closure(Variant, Variant): bool}>
     */
    public static function comparisons(): iterable
    {
        yield 'isAtMost' => [static fn(Variant $variant, Variant $other): bool => $variant->isAtMost($other)];
        yield 'isAtLeast' => [static fn(Variant $variant, Variant $other): bool => $variant->isAtLeast($other)];
        yield 'is' => [static fn(Variant $variant, Variant $other): bool => $variant->is($other)];
    }

    public function test_comparing_two_lists_of_strings_throws(): void
    {
        $this->expectException(LogicException::class);

        self::assertFalse(Variant::ofString('v1', 0, 'documents.v1.variants.values')->isAtMost(Variant::ofString('v1', 0, 'documents.admin.variants.values')));
    }
}
