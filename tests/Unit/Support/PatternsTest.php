<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Support;

use Ark4ne\OpenApi\Support\Patterns;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Patterns::class)]
final class PatternsTest extends TestCase
{
    public function test_a_pattern_is_the_body_of_an_expression_without_flags(): void
    {
        self::assertSame('^[A-Z]', Patterns::ecma('/^[A-Z]/'));
        self::assertSame('a/b', Patterns::ecma('#a/b#'));
        self::assertNull(Patterns::ecma('/^[a-z]/i'));
        self::assertNull(Patterns::ecma('/'));
        self::assertNull(Patterns::ecma(''));
    }
}
