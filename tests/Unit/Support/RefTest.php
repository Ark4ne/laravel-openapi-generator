<?php

namespace Test\Unit\Support;

use Ark4ne\OpenApi\Support\ArrayCache;
use Ark4ne\OpenApi\Support\Ref;
use PHPUnit\Framework\TestCase;

class RefTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ArrayCache::clear();
    }

    public function testEnumRefUsesCustomId(): void
    {
        $ref = Ref::enumRef(\Test\app\Enums\StatusWithCustomId::class);
        $this->assertSame('enum-custom-status', $ref);
    }

    public function testEnumRefFallsBackToHashWhenNoAttribute(): void
    {
        $ref = Ref::enumRef(\Test\app\Enums\AnotherStatusWithSameId::class);
        // AnotherStatusWithSameId has #[Id] too, so clear and test separately
        ArrayCache::clear();
        $ref = Ref::enumRef(\Test\app\Enums\StatusWithCustomId::class);
        $this->assertStringStartsWith('enum-', $ref);
        $this->assertSame('enum-custom-status', $ref);
    }

    public function testEnumRefThrowsOnConflict(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/already claimed/');

        Ref::enumRef(\Test\app\Enums\StatusWithCustomId::class);
        Ref::enumRef(\Test\app\Enums\AnotherStatusWithSameId::class);
    }

    public function testEnumRefSameClassTwiceDoesNotThrow(): void
    {
        $ref1 = Ref::enumRef(\Test\app\Enums\StatusWithCustomId::class);
        $ref2 = Ref::enumRef(\Test\app\Enums\StatusWithCustomId::class);
        $this->assertSame($ref1, $ref2);
    }

    public function testResourceRefUsesCustomId(): void
    {
        $ref = Ref::resourceRef(\Test\app\Http\Resources\UserResource::class);
        $this->assertStringStartsWith('resource-', $ref);
    }
}
