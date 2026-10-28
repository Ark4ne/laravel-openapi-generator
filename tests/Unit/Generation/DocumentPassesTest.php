<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Generation;

use Ark4ne\OpenApi\Description\BodyDescription;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Description\ParameterDescription;
use Ark4ne\OpenApi\Description\RequestDescription;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Ark4ne\OpenApi\Description\TagDescription;
use Ark4ne\OpenApi\Generation\Passes\CleanupPass;
use Ark4ne\OpenApi\Generation\Passes\ComponentExtractionPass;
use Ark4ne\OpenApi\Generation\Passes\OperationIdPass;
use Ark4ne\OpenApi\Generation\Passes\PathParametersPass;
use Ark4ne\OpenApi\Generation\Passes\TagGroupPass;
use Ark4ne\OpenApi\Generation\Passes\TagPass;
use Ark4ne\OpenApi\Generation\SchemaWalker;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Enums\Status;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Models\Post;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Models\User;
use Ark4ne\OpenApi\Tests\Support\Descriptions;
use Ark4ne\OpenApi\Tests\Support\Schemas;
use Ark4ne\OpenApi\Tests\TestCase;
use Ark4ne\OpenApiWriter\Enums\HttpMethod;
use Ark4ne\OpenApiWriter\Objects\Schema;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(TagPass::class)]
#[CoversClass(TagGroupPass::class)]
#[CoversClass(OperationIdPass::class)]
#[CoversClass(ComponentExtractionPass::class)]
#[CoversClass(SchemaWalker::class)]
#[CoversClass(PathParametersPass::class)]
#[CoversClass(CleanupPass::class)]
final class DocumentPassesTest extends TestCase
{
    public function test_the_tags_are_gathered_in_order_with_their_details(): void
    {
        $document = (new TagPass())->process(Descriptions::document(
            Descriptions::operation()->tags('users', 'admin')->declaredTags(TagDescription::create('users')->description('Users')),
            Descriptions::operation(HttpMethod::Post)->tags('users', 'posts')->declaredTags(TagDescription::create('users')->summary('People')),
        )->tags(TagDescription::create('admin')->description('Declared by the document')), $this->context());

        self::assertSame(['admin', 'users', 'posts'], array_map(static fn(TagDescription $tag): string => $tag->name, $document->tags));
        self::assertSame(['Declared by the document', 'Users', null], array_map(static fn(TagDescription $tag): ?string => $tag->description, $document->tags));
        self::assertSame('People', $document->tags[1]->summary);
    }

    public function test_the_parents_of_the_tags_are_declared_tags(): void
    {
        $document = (new TagGroupPass())->process(DocumentDescription::create()->tags(TagDescription::create('users')->parent('Admin'), TagDescription::create('Admin')->description('Back office'), TagDescription::create('posts')->parent('Blog')), $this->context());

        self::assertSame(['users', 'Admin', 'posts', 'Blog'], array_map(static fn(TagDescription $tag): string => $tag->name, $document->tags));
        self::assertSame('Back office', $document->tags[1]->description);
    }

    public function test_the_operation_ids_are_unique(): void
    {
        $context = $this->context();

        $document = (new OperationIdPass())->process(Descriptions::document(
            Descriptions::operation()->operationId('users.index'),
            Descriptions::operation(HttpMethod::Head)->operationId('users.index'),
            Descriptions::operation(HttpMethod::Post)->operationId('users.index'),
            Descriptions::operation(HttpMethod::Delete),
        ), $context);

        self::assertSame(['users.index', 'users.index_2', 'users.index_3', 'deleteApiUsers'], array_map(static fn(OperationDescription $o): ?string => $o->operationId, $document->operations));
        self::assertCount(2, $context->diagnostics);
        self::assertSame('[warning] pass HEAD /api/users: operationId users.index already used, renamed users.index_2', (string) $context->diagnostics->all()[0]);
    }

    public function test_the_components_of_the_context_and_the_enums_of_the_path_become_schemas(): void
    {
        $context = $this->context();
        $context->components->register(User::class, 'User', Schema::object()->properties(Schema::integer('id')));
        $operation = Descriptions::operation(HttpMethod::Get, '/api/posts/{status}')
            ->request(RequestDescription::create()->parameters(ParameterDescription::path('status', Schema::string()->enum('draft', 'published'))->boundTo(Status::class)));

        $document = (new ComponentExtractionPass())->process(Descriptions::document($operation), $context);

        self::assertSame(['User', 'Status'], array_keys($document->schemas));
        Schemas::assertSame(['$ref' => '#/components/schemas/Status'], $document->operations[0]->request->parameters[0]->schema);
        Schemas::assertSame(['type' => 'string', 'enum' => ['draft', 'published']], $document->schemas['Status']);
    }

    public function test_identical_components_are_kept_once_and_their_references_follow(): void
    {
        $context = $this->context();
        $context->components->register(User::class, 'User', Schema::object()->properties(Schema::integer('id')));
        $context->components->register(Post::class, 'Author', Schema::object()->properties(Schema::integer('id')));
        $operation = Descriptions::operation()->responses(ResponseDescription::ok()->content('application/json', Schema::object()->properties(Schema::array('items')->items(Schema::ref('Author')))));

        $document = (new ComponentExtractionPass())->process(Descriptions::document($operation), $context);

        self::assertSame(['User'], array_keys($document->schemas));
        Schemas::assertSame('{"type":"object","properties":{"items":{"type":"array","items":{"$ref":"#/components/schemas/User"}}}}', $document->operations[0]->responses[0]->contents['application/json'] ?? Schema::create());
    }

    public function test_the_components_are_written_inline_without_references(): void
    {
        $context = $this->context(useReferences: false);
        $context->components->register(User::class, 'User', Schema::object()->properties(Schema::integer('id')));
        $operation = Descriptions::operation()->request(RequestDescription::create()->body(BodyDescription::create()->content('application/json', Schema::ref('User'))));

        $document = (new ComponentExtractionPass())->process(Descriptions::document($operation), $context);

        self::assertSame([], $document->schemas);
        Schemas::assertSame('{"type":"object","properties":{"id":{"type":"integer"}}}', $document->operations[0]->request->body->contents['application/json'] ?? Schema::create());
    }

    public function test_the_path_parameters_shared_by_every_operation_of_a_path_go_up(): void
    {
        $user = ParameterDescription::path('user', Schema::integer());
        $document = (new PathParametersPass())->process(Descriptions::document(
            Descriptions::operation(HttpMethod::Get, '/api/users/{user}')->request(RequestDescription::create()->parameters($user, ParameterDescription::query('with'))),
            Descriptions::operation(HttpMethod::Put, '/api/users/{user}')->request(RequestDescription::create()->parameters($user)),
            Descriptions::operation(HttpMethod::Get, '/api/posts/{post}')->request(RequestDescription::create()->parameters(ParameterDescription::path('post'))),
        ), $this->context());

        self::assertSame(['/api/users/{user}', '/api/posts/{post}'], array_keys($document->pathParameters));
        self::assertSame(['with'], array_map(static fn(ParameterDescription $p): string => $p->name, $document->operations[0]->request->parameters));
        self::assertSame([], $document->operations[1]->request->parameters);
    }

    public function test_the_cleanup_removes_duplicated_tags_and_gives_a_response_to_every_operation(): void
    {
        $document = (new CleanupPass())->process(Descriptions::document(
            Descriptions::operation()->tags('users', 'users', 'admin'),
            OperationDescription::create(HttpMethod::Get, '/api/ping'),
        ), $this->context());

        self::assertSame(['users', 'admin'], $document->operations[0]->tags);
        self::assertSame(['default'], array_map(static fn(ResponseDescription $r): int|string => $r->status, $document->operations[1]->responses));
    }
}
