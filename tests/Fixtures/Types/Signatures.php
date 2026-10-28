<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Types;

use Ark4ne\OpenApi\Tests\Fixtures\Routing\Enums\Status;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Models as M;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Models\{Post, User as Author};
use Countable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use LogicException;
use RuntimeException;

/**
 * One method per form of type read by the TypeResolver.
 *
 * @template TKey of array-key
 */
class Signatures
{
    public const KIND_A = 'a';

    public const KIND_B = 'b';

    public const LIMIT = 10;

    /** @var list<Author> */
    public array $authors = [];

    public ?int $count = null;

    public function native(): int
    {
        return 1;
    }

    public function nativeNullable(): ?string
    {
        return null;
    }

    public function nativeUnion(): int|string|null
    {
        return 1;
    }

    public function nativeSelf(): self
    {
        return $this;
    }

    public function nativeStatic(): static
    {
        return $this;
    }

    public function nativeClass(): Post
    {
        return new Post();
    }

    public function nothing() {}

    /**
     * @return Author
     */
    public function imported(): object
    {
        return new Author();
    }

    /**
     * @return M\Post
     */
    public function importedNamespace(): object
    {
        return new Post();
    }

    /**
     * @return \Ark4ne\OpenApi\Tests\Fixtures\Routing\Models\Order
     */
    public function fullyQualified(): object
    {
        return new M\Order();
    }

    /**
     * @return Sibling
     */
    public function sameNamespace(): object
    {
        return new Sibling();
    }

    /**
     * @return Collection<int, Author>
     */
    public function generic(): Collection
    {
        return new Collection();
    }

    /**
     * @return AnonymousResourceCollection<Author>
     */
    public function resourceCollection(): AnonymousResourceCollection
    {
        return new AnonymousResourceCollection([], Author::class);
    }

    /**
     * @return LengthAwarePaginator<int, Post>
     */
    public function paginator(): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, 15);
    }

    /**
     * @return list<Post>
     */
    public function list(): array
    {
        return [];
    }

    /**
     * @return non-empty-list<int>
     */
    public function nonEmptyList(): array
    {
        return [1];
    }

    /**
     * @return Post[]
     */
    public function brackets(): array
    {
        return [];
    }

    /**
     * @return array<string, int>
     */
    public function map(): array
    {
        return [];
    }

    /**
     * @return array{id: int, name?: string, 0: bool}
     */
    public function shape(): array
    {
        return ['id' => 1, 0 => true];
    }

    /**
     * @return list{int, string}
     */
    public function listShape(): array
    {
        return [1, ''];
    }

    /**
     * @return array{id: int, ...}
     */
    public function unsealedShape(): array
    {
        return ['id' => 1];
    }

    /**
     * @return Post|Author|null
     */
    public function union(): ?object
    {
        return null;
    }

    /**
     * @return Post&Countable
     */
    public function intersection(): object
    {
        return new Post();
    }

    /**
     * @return 'draft'|'published'
     */
    public function literals(): string
    {
        return 'draft';
    }

    /**
     * @return 1|2.5|true|false|null
     */
    public function scalarLiterals(): mixed
    {
        return 1;
    }

    /**
     * @return self::KIND_*
     */
    public function constants(): string
    {
        return self::KIND_A;
    }

    /**
     * @return Status::Draft
     */
    public function enumCase(): Status
    {
        return Status::Draft;
    }

    /**
     * @return $this
     */
    public function this(): static
    {
        return $this;
    }

    /**
     * @return integer|boolean|double
     */
    public function aliases(): int|bool|float
    {
        return 1;
    }

    /**
     * @return positive-int
     */
    public function pseudo(): int
    {
        return 1;
    }

    /**
     * @return int<0, max>
     */
    public function range(): int
    {
        return 1;
    }

    /**
     * @return class-string<Post>
     */
    public function classString(): string
    {
        return Post::class;
    }

    /**
     * @return callable(int): void
     */
    public function callable(): callable
    {
        return static function (int $value): void {};
    }

    /**
     * @template T of Post
     *
     * @return T
     */
    public function template(): object
    {
        return new Post();
    }

    /**
     * @return TKey
     */
    public function classTemplate(): int|string
    {
        return 1;
    }

    /**
     * @template T
     *
     * @return T
     */
    public function unboundTemplate(): mixed
    {
        return null;
    }

    /**
     * @return ($flag is true ? int : string)
     */
    public function conditional(bool $flag): int|string
    {
        return $flag ? 1 : '';
    }

    /**
     * @return mixed
     */
    public function mixed(): mixed
    {
        return null;
    }

    /**
     * @return object{id: int}
     */
    public function objectShape(): object
    {
        return (object) ['id' => 1];
    }

    /**
     * @param list<Author> $authors
     */
    public function parameters(array $authors, ?Post $post, $untyped): void {}

    /**
     * @throws RuntimeException|LogicException
     * @throws M\Broken
     */
    public function throws(): void {}
}
