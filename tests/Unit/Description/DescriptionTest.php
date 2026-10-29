<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Description;

use Ark4ne\OpenApi\Description\Description;
use Ark4ne\OpenApi\Description\ParameterDescription;
use Ark4ne\OpenApiWriter\Objects\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Description::class)]
final class DescriptionTest extends TestCase
{
    public function test_a_setter_returns_a_copy_with_the_changed_property(): void
    {
        $original = ParameterDescription::path('id');

        $copy = $original->description('Identifier')->required(false);

        self::assertNotSame($original, $copy);
        self::assertSame('Identifier', $copy->description);
        self::assertFalse($copy->required);
        self::assertSame('id', $copy->name);
    }

    public function test_a_setter_leaves_the_original_unchanged(): void
    {
        $original = ParameterDescription::path('id', Schema::integer());

        $original->description('Identifier')->schema(Schema::string());

        self::assertNull($original->description);
        self::assertSame(['type' => 'integer'], $original->schema->toArray());
    }
}
