<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Generation;

use Ark4ne\OpenApi\Generation\SchemaWalker;
use Ark4ne\OpenApiWriter\Objects\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SchemaWalker::class)]
final class SchemaWalkerTest extends TestCase
{
    public function test_a_reference_to_a_component_schema_names_it(): void
    {
        self::assertSame('User', SchemaWalker::component(Schema::ref('User')));
    }

    public function test_any_other_schema_names_no_component(): void
    {
        self::assertNull(SchemaWalker::component(Schema::object()));
        self::assertNull(SchemaWalker::component(Schema::ref('https://example.com/user.json')));
        self::assertNull(SchemaWalker::component(Schema::ref('#/components/responses/Error')));
    }
}
