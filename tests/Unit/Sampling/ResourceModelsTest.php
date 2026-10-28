<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Sampling;

use Ark4ne\OpenApi\Sampling\ResourceModels;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Models\Post;
use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Http\Resources\AuthorCollection;
use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Http\Resources\AuthorResource;
use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Http\Resources\ConstructorResource;
use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Http\Resources\ExplicitCollection;
use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Http\Resources\MixinResource;
use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Http\Resources\OrphanResource;
use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Http\Resources\UsedResource;
use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Models\Author;
use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Models\Tagged;
use Ark4ne\OpenApi\Tests\Fixtures\Types\GenericCollection;
use Ark4ne\OpenApi\Tests\Fixtures\Types\PostResource;
use Ark4ne\OpenApi\Types\TypeResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResourceModels::class)]
final class ResourceModelsTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string, class-string|null}>
     */
    public static function models(): iterable
    {
        yield 'constructor type' => [ConstructorResource::class, Author::class];
        yield '@extends JsonResource<Post>' => [PostResource::class, Post::class];
        yield '@mixin' => [MixinResource::class, Author::class];
        yield '#[UseResource] on a loaded model' => [UsedResource::class, Tagged::class];
        yield 'naming convention' => [AuthorResource::class, Author::class];
        yield 'none' => [OrphanResource::class, null];
    }

    /**
     * @param class-string $resource
     * @param class-string|null $model
     */
    #[DataProvider('models')]
    public function test_the_model_of_a_resource_is_found(string $resource, ?string $model): void
    {
        class_exists(Tagged::class);

        self::assertSame($model, (new ResourceModels(new TypeResolver()))->model($resource));
    }

    /**
     * @return iterable<string, array{class-string, class-string|null}>
     */
    public static function collections(): iterable
    {
        yield '$collects' => [ExplicitCollection::class, MixinResource::class];
        yield '@extends ResourceCollection<PostResource>' => [GenericCollection::class, PostResource::class];
        yield 'convention of Laravel' => [AuthorCollection::class, AuthorResource::class];
    }

    /**
     * @param class-string $collection
     * @param class-string|null $resource
     */
    #[DataProvider('collections')]
    public function test_the_resource_of_a_collection_is_found(string $collection, ?string $resource): void
    {
        self::assertSame($resource, (new ResourceModels(new TypeResolver()))->collected($collection));
    }
}
