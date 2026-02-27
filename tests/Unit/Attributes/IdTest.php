<?php

namespace Test\Unit\Attributes;

use Ark4ne\OpenApi\Attributes\Id;
use PHPUnit\Framework\TestCase;

class IdTest extends TestCase
{
    public function testIdAttributeStoresId(): void
    {
        $attr = new Id('my-id');
        $this->assertSame('my-id', $attr->id);
    }

    public function testIdAttributeIsTargetClass(): void
    {
        $reflection = new \ReflectionClass(Id::class);
        $attributes = $reflection->getAttributes(\Attribute::class);
        $this->assertNotEmpty($attributes);
        $flags = $attributes[0]->newInstance()->flags;
        $this->assertSame(\Attribute::TARGET_CLASS, $flags);
    }
}
