<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Types;

use Ark4ne\OpenApi\Tests\Fixtures\Routing\Models\Post;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Models\User;
use Ark4ne\OpenApi\Tests\Fixtures\Types\BaseResource;
use Ark4ne\OpenApi\Tests\Fixtures\Types\ConcreteCovariantResource;
use Ark4ne\OpenApi\Tests\Fixtures\Types\Documented;
use Ark4ne\OpenApi\Tests\Fixtures\Types\Employee;
use Ark4ne\OpenApi\Tests\Fixtures\Types\InheritedResource;
use Ark4ne\OpenApi\Tests\Fixtures\Types\PlainResource;
use Ark4ne\OpenApi\Tests\Fixtures\Types\PostResource;
use Ark4ne\OpenApi\Tests\Fixtures\Types\Signatures;
use Ark4ne\OpenApi\Tests\Fixtures\Types\UserResource;
use Ark4ne\OpenApi\Tests\Fixtures\Types\UsesTrait;
use Ark4ne\OpenApi\Types\ArrayShapeItem;
use Ark4ne\OpenApi\Types\ArrayShapeType;
use Ark4ne\OpenApi\Types\DocBlock;
use Ark4ne\OpenApi\Types\GenericType;
use Ark4ne\OpenApi\Types\IntersectionType;
use Ark4ne\OpenApi\Types\ListType;
use Ark4ne\OpenApi\Types\LiteralType;
use Ark4ne\OpenApi\Types\MixedType;
use Ark4ne\OpenApi\Types\NamedType;
use Ark4ne\OpenApi\Types\NullableType;
use Ark4ne\OpenApi\Types\Type;
use Ark4ne\OpenApi\Types\TypeResolver;
use Ark4ne\OpenApi\Types\TypeScope;
use Ark4ne\OpenApi\Types\UnionType;
use Ark4ne\OpenApi\Types\UseStatements;
use Closure;
use Illuminate\Http\Resources\Json\JsonResource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;

#[CoversClass(TypeResolver::class)]
#[CoversClass(TypeScope::class)]
#[CoversClass(UseStatements::class)]
#[CoversClass(DocBlock::class)]
#[CoversClass(NamedType::class)]
#[CoversClass(GenericType::class)]
#[CoversClass(ListType::class)]
#[CoversClass(ArrayShapeType::class)]
#[CoversClass(ArrayShapeItem::class)]
#[CoversClass(UnionType::class)]
#[CoversClass(IntersectionType::class)]
#[CoversClass(NullableType::class)]
#[CoversClass(LiteralType::class)]
#[CoversClass(MixedType::class)]
final class TypeResolverTest extends TestCase
{
    private const MODELS = 'Ark4ne\OpenApi\Tests\Fixtures\Routing\Models\\';

    private const TYPES = 'Ark4ne\OpenApi\Tests\Fixtures\Types\\';

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function returnTypes(): iterable
    {
        yield 'native' => ['native', 'int'];
        yield 'native nullable' => ['nativeNullable', '?string'];
        yield 'native union with null, in the order of PHP' => ['nativeUnion', 'string|int|null'];
        yield 'native self' => ['nativeSelf', self::TYPES . 'Signatures'];
        yield 'native static' => ['nativeStatic', self::TYPES . 'Signatures'];
        yield 'native class' => ['nativeClass', self::MODELS . 'Post'];
        yield 'no type' => ['nothing', null];
        yield 'imported alias in a group use' => ['imported', self::MODELS . 'User'];
        yield 'imported namespace alias' => ['importedNamespace', self::MODELS . 'Post'];
        yield 'fully qualified' => ['fullyQualified', self::MODELS . 'Order'];
        yield 'same namespace' => ['sameNamespace', self::TYPES . 'Sibling'];
        yield 'Eloquent collection' => ['generic', 'Illuminate\Database\Eloquent\Collection<int, ' . self::MODELS . 'User>'];
        yield 'resource collection' => ['resourceCollection', 'Illuminate\Http\Resources\Json\AnonymousResourceCollection<' . self::MODELS . 'User>'];
        yield 'paginator' => ['paginator', 'Illuminate\Pagination\LengthAwarePaginator<int, ' . self::MODELS . 'Post>'];
        yield 'list' => ['list', 'list<' . self::MODELS . 'Post>'];
        yield 'non-empty list' => ['nonEmptyList', 'non-empty-list<int>'];
        yield 'brackets' => ['brackets', 'list<' . self::MODELS . 'Post>'];
        yield 'map' => ['map', 'array<string, int>'];
        yield 'array shape' => ['shape', 'array{id: int, name?: string, 0: bool}'];
        yield 'list shape' => ['listShape', 'list{int, string}'];
        yield 'unsealed shape' => ['unsealedShape', 'array{id: int, ...}'];
        yield 'union' => ['union', self::MODELS . 'Post|' . self::MODELS . 'User|null'];
        yield 'intersection' => ['intersection', self::MODELS . 'Post&Countable'];
        yield 'string literals' => ['literals', "'draft'|'published'"];
        yield 'scalar literals' => ['scalarLiterals', '1|2.5|true|false|null'];
        yield 'class constants' => ['constants', "'a'|'b'"];
        yield 'enum case' => ['enumCase', "'draft'"];
        yield '$this' => ['this', self::TYPES . 'Signatures'];
        yield 'aliases' => ['aliases', 'int|bool|float'];
        yield 'pseudo type' => ['pseudo', 'positive-int'];
        yield 'integer range' => ['range', 'int<0, max>'];
        yield 'class string' => ['classString', 'class-string<' . self::MODELS . 'Post>'];
        yield 'callable' => ['callable', 'callable'];
        yield 'bound method template' => ['template', self::MODELS . 'Post'];
        yield 'bound class template' => ['classTemplate', 'array-key'];
        yield 'unbound template' => ['unboundTemplate', 'mixed'];
        yield 'conditional' => ['conditional', 'int|string'];
        yield 'mixed' => ['mixed', 'mixed'];
        yield 'object shape' => ['objectShape', 'object'];
    }

    #[DataProvider('returnTypes')]
    public function test_the_return_type_is_read_from_the_phpdoc_then_the_declaration(string $method, ?string $expected): void
    {
        self::assertSame($expected, (new TypeResolver())->returnType(new ReflectionMethod(Signatures::class, $method))?->toString());
    }

    public function test_the_tree_of_a_type_is_built(): void
    {
        $resolver = new TypeResolver();

        self::assertEquals(
            new NullableType(new UnionType([new NamedType(Post::class), new NamedType(User::class)])),
            $resolver->returnType(new ReflectionMethod(Signatures::class, 'union')),
        );
        self::assertEquals(
            new ArrayShapeType([new ArrayShapeItem('id', new NamedType('int')), new ArrayShapeItem('name', new NamedType('string'), true), new ArrayShapeItem(0, new NamedType('bool'))]),
            $resolver->returnType(new ReflectionMethod(Signatures::class, 'shape')),
        );
        self::assertEquals(new ListType(new NamedType('int'), nonEmpty: true), $resolver->returnType(new ReflectionMethod(Signatures::class, 'nonEmptyList')));
    }

    public function test_a_parameter_type_is_read_from_its_param_tag_then_its_declaration(): void
    {
        $resolver = new TypeResolver();
        $parameters = (new ReflectionMethod(Signatures::class, 'parameters'))->getParameters();

        self::assertSame(
            ['list<' . self::MODELS . 'User>', '?' . self::MODELS . 'Post', null],
            array_map(static fn(ReflectionParameter $parameter): ?string => $resolver->parameterType($parameter)?->toString(), $parameters),
        );
    }

    public function test_a_property_type_is_read_from_its_var_tag_then_its_declaration(): void
    {
        $resolver = new TypeResolver();

        self::assertSame('list<' . self::MODELS . 'User>', $resolver->propertyType(new ReflectionProperty(Signatures::class, 'authors'))?->toString());
        self::assertSame('?int', $resolver->propertyType(new ReflectionProperty(Signatures::class, 'count'))?->toString());
    }

    public function test_the_thrown_exceptions_are_read_with_unions_flattened(): void
    {
        $throws = (new TypeResolver())->throws(new ReflectionMethod(Signatures::class, 'throws'));

        self::assertSame(['RuntimeException', 'LogicException', self::MODELS . 'Broken'], array_map(static fn(Type $type): string => $type->toString(), $throws));
    }

    public function test_the_magic_properties_of_a_class_and_its_parents_are_read(): void
    {
        $properties = (new TypeResolver())->properties(new ReflectionClass(Employee::class));

        self::assertSame(['id' => 'string', 'email' => 'string', 'admin' => 'bool'], array_map(static fn(Type $type): string => $type->toString(), $properties));
    }

    /**
     * @return iterable<string, array{class-string, class-string, list<string>}>
     */
    public static function genericArguments(): iterable
    {
        yield 'direct' => [PostResource::class, JsonResource::class, [self::MODELS . 'Post']];
        yield 'through a template of the parent' => [UserResource::class, JsonResource::class, [self::MODELS . 'User']];
        yield 'of the parent itself' => [UserResource::class, BaseResource::class, [self::MODELS . 'User', 'int']];
        yield 'inherited from a parent without phpdoc' => [InheritedResource::class, JsonResource::class, [self::MODELS . 'Post']];
        yield 'none declared' => [PlainResource::class, JsonResource::class, []];
    }

    /**
     * @param class-string $class
     * @param class-string $ancestor
     * @param list<string> $expected
     */
    #[DataProvider('genericArguments')]
    public function test_the_type_arguments_given_to_an_ancestor_are_resolved(string $class, string $ancestor, array $expected): void
    {
        $arguments = (new TypeResolver())->genericArguments(new ReflectionClass($class), $ancestor);

        self::assertSame($expected, array_map(static fn(Type $type): string => $type->toString(), $arguments));
    }

    public function test_a_closure_resolves_the_imports_of_its_file(): void
    {
        $closure = /** @return list<Post> */ static fn(): array => [];

        self::assertSame('list<' . self::MODELS . 'Post>', (new TypeResolver())->returnType(new ReflectionFunction($closure))?->toString());
    }

    public function test_a_method_of_a_trait_resolves_the_imports_of_the_trait(): void
    {
        $type = (new TypeResolver())->returnType(new ReflectionMethod(UsesTrait::class, 'posts'));

        self::assertSame('list<' . self::MODELS . 'Post>', $type?->toString());
    }

    public function test_a_closure_resolves_the_namespace_of_its_file(): void
    {
        $closure = require dirname(__DIR__, 2) . '/Fixtures/Types/closures.php';
        self::assertInstanceOf(Closure::class, $closure);

        self::assertSame(self::TYPES . 'Sibling', (new TypeResolver())->returnType(new ReflectionFunction($closure))?->toString());
    }

    public function test_the_imports_of_a_braced_namespace_are_read(): void
    {
        require_once dirname(__DIR__, 2) . '/Fixtures/Types/braced.php';

        $type = (new TypeResolver())->returnType(new ReflectionMethod('Ark4ne\OpenApi\Tests\Fixtures\Types\Braced\BracedSignatures', 'user'));

        self::assertSame(self::MODELS . 'User', $type?->toString());
    }

    public function test_the_covariant_templates_keep_their_position(): void
    {
        $arguments = (new TypeResolver())->genericArguments(new ReflectionClass(ConcreteCovariantResource::class), JsonResource::class);

        self::assertSame([self::MODELS . 'Post'], array_map(static fn(Type $type): string => $type->toString(), $arguments));
    }

    public function test_a_type_can_be_parsed_in_the_scope_of_a_class(): void
    {
        $resolver = new TypeResolver();

        self::assertSame('?' . self::MODELS . 'User', $resolver->parse('Author|null', new ReflectionClass(Signatures::class))->toString());
        self::assertInstanceOf(MixedType::class, $resolver->parse('array{', new ReflectionClass(Signatures::class)));
    }

    /**
     * @return iterable<string, array{string, string|null, string|null, bool}>
     */
    public static function docBlocks(): iterable
    {
        yield 'summary ended by a sentence at the end of a line' => ['index', 'List the users.', "Second line of the summary.\n\nFirst paragraph\nof the description.\n\nSecond paragraph.", true];
        yield 'sentences on one line' => ['show', 'Show a user. More details on the same line.', null, false];
        yield 'sentence then a line' => ['store', 'Store a user.', 'Details on the next line.', false];
        yield 'tags only' => ['tagsOnly', null, null, false];
        yield 'no comment' => ['none', null, null, false];
    }

    #[DataProvider('docBlocks')]
    public function test_the_summary_and_the_description_are_split(string $method, ?string $summary, ?string $description, bool $deprecated): void
    {
        $doc = (new TypeResolver())->docBlock(new ReflectionMethod(Documented::class, $method));

        self::assertSame($summary, $doc->summary());
        self::assertSame($description, $doc->description());
        self::assertSame($deprecated, $doc->isDeprecated());
        self::assertNull($doc->deprecation());
    }

    public function test_the_deprecation_message_is_read(): void
    {
        $doc = (new TypeResolver())->docBlock(new ReflectionClass(Documented::class));

        self::assertSame('Lists the users. Sorted by name.', $doc->summary());
        self::assertSame('use v2', $doc->deprecation());
    }

    public function test_a_comment_is_parsed_once_per_resolver(): void
    {
        $resolver = new TypeResolver();
        $method = new ReflectionMethod(Documented::class, 'index');

        self::assertSame($resolver->docBlock($method), $resolver->docBlock($method));
    }

    public function test_the_named_types_know_whether_they_are_classes(): void
    {
        self::assertTrue((new NamedType('int'))->isBuiltin());
        self::assertFalse((new NamedType('int'))->isClass());
        self::assertTrue((new NamedType(Post::class))->isClass());
        self::assertFalse((new NamedType('App\Missing'))->isClass());
        self::assertSame('int', (new GenericType('int', [new LiteralType(0), new NamedType('max')]))->base()->toString());
    }

    public function test_the_types_are_written_back(): void
    {
        self::assertSame("'it\\'s'", (new LiteralType("it's"))->toString());
        self::assertSame('(A&B)|C', (new UnionType([new IntersectionType([new NamedType('A'), new NamedType('B')]), new NamedType('C')]))->toString());
        self::assertSame('array{id: int}|null', (new NullableType(new ArrayShapeType([new ArrayShapeItem('id', new NamedType('int'))])))->toString());
        self::assertSame('?list<int>', (new NullableType(new ListType(new NamedType('int'))))->toString());
    }
}
