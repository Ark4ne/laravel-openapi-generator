<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Context;

use Ark4ne\OpenApi\Context\ComponentRegistry;
use Ark4ne\OpenApi\Context\Diagnostics;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Models\Post;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Models\User;
use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Models\Author;
use Ark4ne\OpenApiWriter\Objects\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ComponentRegistry::class)]
final class ComponentRegistryTest extends TestCase
{
    public function test_a_class_is_registered_under_its_name(): void
    {
        $registry = new ComponentRegistry();

        self::assertSame('User', $registry->register(User::class, 'User', Schema::object()));
        self::assertSame('User', $registry->nameOf(User::class));
        self::assertTrue($registry->has('User'));
        self::assertSame(['type' => 'object'], $registry->get('User')?->toArray());
        self::assertNull($registry->nameOf(Post::class));
        self::assertNull($registry->get('Post'));
    }

    public function test_a_class_registered_again_keeps_its_name_and_takes_the_new_schema(): void
    {
        $registry = new ComponentRegistry();
        $registry->register(User::class, 'User', Schema::object());

        self::assertSame('User', $registry->register(User::class, 'Other', Schema::string()));
        self::assertSame(['type' => 'string'], $registry->get('User')?->toArray());
        self::assertSame(['User'], array_keys($registry->all()));
    }

    public function test_a_name_taken_by_another_class_gets_a_suffix_and_a_warning(): void
    {
        $diagnostics = new Diagnostics();
        $registry = new ComponentRegistry($diagnostics);
        $registry->register(User::class, 'Person', Schema::object());

        self::assertSame('Person2', $registry->register(Author::class, 'Person', Schema::object()));
        self::assertSame('Person3', $registry->register(Post::class, 'Person', Schema::object()));
        self::assertSame(['Person', 'Person2', 'Person3'], array_keys($registry->all()));
        self::assertSame('[warning] pass: component Person already describes ' . User::class . ', ' . Author::class . ' is named Person2', (string) $diagnostics->all()[0]);
    }
}
