<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Extraction\Responses;

use Ark4ne\OpenApi\Configuration\ExceptionsConfiguration;
use Ark4ne\OpenApi\Configuration\SamplingConfiguration;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\HeaderDescription;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Ark4ne\OpenApi\Description\ResponseOrigin;
use Ark4ne\OpenApi\Extraction\OperationContextFactory;
use Ark4ne\OpenApi\Extraction\Responses\OperationResponses;
use Ark4ne\OpenApi\Sampling\RelationLoader;
use Ark4ne\OpenApi\Sampling\Samplers;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\ChildResponseController;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\Exceptions\PaymentRequiredException;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\Exceptions\UnknownException;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\ResponseController;
use Ark4ne\OpenApi\Tests\Fixtures\Types\Signatures;
use Ark4ne\OpenApi\Tests\Support\Schemas;
use Ark4ne\OpenApi\Tests\Unit\Sampling\SamplingTestCase;
use Ark4ne\OpenApi\Types\TypeResolver;
use Ark4ne\OpenApiWriter\Objects\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversNamespace;

/**
 * Every kind of response, through the extractors, the declared responses and the declared exceptions.
 */
#[CoversNamespace('Ark4ne\OpenApi\Extraction\Responses')]
#[CoversNamespace('Ark4ne\OpenApi\Extraction\Exceptions')]
#[CoversClass(RelationLoader::class)]
final class OperationResponsesTest extends SamplingTestCase
{
    private const ARTICLE = '{"type":"object","properties":{"id":{"type":"integer"},"title":{"type":"string"},"author":{"$ref":"#/components/schemas/Author"}},"required":["id","title"]}';

    private const AUTHOR = '{"type":"object","properties":{"id":{"type":"integer"},"name":{"type":"string"}},"required":["id","name"]}';

    private const LINKS = '{"type":"array","items":{"type":"object","properties":{"url":{"type":["string","null"]},"label":{"type":"string"},"page":{"type":["integer","null"]},"active":{"type":"boolean"}},"required":["url","label","page","active"]}}';

    private ?GenerationContext $context = null;

    public function test_a_resource_is_wrapped_and_becomes_a_component_with_its_relations(): void
    {
        $response = $this->only('get', 'api/articles/{article}', 'show');

        self::assertSame(200, $response->status);
        self::assertSame('OK', $response->description);
        self::assertSame(ResponseOrigin::Inferred, $response->origin);
        Schemas::assertSame('{"type":"object","properties":{"data":{"$ref":"#/components/schemas/Article"},"meta":{"type":"object","properties":{"version":{"type":"string"}},"required":["version"]}},"required":["data","meta"]}', $this->jsonSchema($response));
        Schemas::assertSame(self::ARTICLE, $this->componentSchema('Article'));
        Schemas::assertSame(self::AUTHOR, $this->componentSchema('Author'));
    }

    public function test_a_resource_returned_by_post_is_created(): void
    {
        $response = $this->only('post', 'api/articles', 'store');

        self::assertSame(201, $response->status);
        self::assertSame('Created', $response->description);
    }

    public function test_a_resource_can_be_written_inline(): void
    {
        $response = $this->only('get', 'api/articles/{article}', 'show', $this->ctx(useReferences: false));

        Schemas::assertSame('{"type":"object","properties":{"data":' . str_replace('{"$ref":"#/components/schemas/Author"}', self::AUTHOR, self::ARTICLE) . ',"meta":{"type":"object","properties":{"version":{"type":"string"}},"required":["version"]}},"required":["data","meta"]}', $this->jsonSchema($response));
    }

    public function test_a_collection_wraps_a_list_of_its_resource(): void
    {
        foreach (['index', 'anonymous'] as $action) {
            $this->context = null;
            Schemas::assertSame('{"type":"object","properties":{"data":{"type":"array","items":{"$ref":"#/components/schemas/Article"}}},"required":["data"]}', $this->jsonSchema($this->only('get', "api/{$action}", $action)), $action);
        }
    }

    public function test_a_length_aware_paginated_collection_has_links_and_meta(): void
    {
        Schemas::assertSame(
            '{"type":"object","properties":{"data":{"type":"array","items":{"$ref":"#/components/schemas/Article"}},"links":{"type":"object","properties":{"first":{"type":["string","null"]},"last":{"type":["string","null"]},"prev":{"type":["string","null"]},"next":{"type":["string","null"]}},"required":["first","last","prev","next"]},"meta":{"type":"object","properties":{"current_page":{"type":"integer"},"from":{"type":["integer","null"]},"last_page":{"type":"integer"},"links":' . self::LINKS . ',"path":{"type":["string","null"]},"per_page":{"type":"integer"},"to":{"type":["integer","null"]},"total":{"type":"integer"}},"required":["current_page","from","last_page","links","path","per_page","to","total"]}},"required":["data","links","meta"]}',
            $this->jsonSchema($this->only('get', 'api/paginated', 'paginated')),
        );
    }

    public function test_a_simple_and_a_cursor_paginated_collection_have_their_meta(): void
    {
        $simple = $this->jsonSchema($this->only('get', 'api/simple', 'simple'));
        $this->context = null;
        $cursor = $this->jsonSchema($this->only('get', 'api/cursor', 'cursor'));

        self::assertSame(['current_page', 'current_page_url', 'from', 'path', 'per_page', 'to'], self::keys(self::at($simple, 'meta')));
        self::assertSame(['path', 'per_page', 'next_cursor', 'prev_cursor'], self::keys(self::at($cursor, 'meta')));
    }

    public function test_a_paginator_returned_as_it_is_has_the_shape_of_laravel(): void
    {
        $schema = $this->jsonSchema($this->only('get', 'api/raw', 'raw'));

        self::assertSame(['current_page', 'data', 'first_page_url', 'from', 'last_page', 'last_page_url', 'links', 'next_page_url', 'path', 'per_page', 'prev_page_url', 'to', 'total'], self::keys($schema));
        self::assertContains('title', self::keys(self::at($schema, 'data', '*')));
    }

    public function test_a_model_and_a_collection_of_models_are_their_attributes(): void
    {
        $model = $this->jsonSchema($this->only('get', 'api/model', 'model'));
        $this->context = null;
        $models = $this->jsonSchema($this->only('get', 'api/models', 'models'));

        self::assertContains('title', self::keys($model));
        self::assertContains('title', self::keys(self::at($models, '*')));
    }

    public function test_an_array_shape_and_a_string(): void
    {
        Schemas::assertSame('{"type":"object","properties":{"id":{"type":"integer"},"name":{"type":"string"}},"required":["id","name"],"additionalProperties":false}', $this->jsonSchema($this->only('get', 'api/shape', 'shape')));
        $this->context = null;
        Schemas::assertSame('{"type":"string"}', $this->only('get', 'api/text', 'text')->contents['text/html'] ?? Schema::create());
    }

    public function test_a_json_response_without_declaration_is_a_free_object_with_an_info(): void
    {
        Schemas::assertSame('{"type":"object"}', $this->jsonSchema($this->only('get', 'api/json', 'json')));
        self::assertDiagnostics(['[info] response GET /api/json: JsonResponse without #[Response], body described as a free object'], $this->ctx());
    }

    public function test_a_declared_body_replaces_the_inferred_one(): void
    {
        $response = $this->only('get', 'api/json', 'declaredJson');

        self::assertSame(ResponseOrigin::Declared, $response->origin);
        Schemas::assertSame('{"type":"object","properties":{"ok":{"type":"boolean"}},"required":["ok"],"additionalProperties":false}', $this->jsonSchema($response));
        self::assertDiagnostics([], $this->ctx());
    }

    public function test_a_declared_success_replaces_the_success_inferred_for_another_status(): void
    {
        $responses = $this->responses('post', 'api/json', 'createdJson');

        self::assertSame([201], array_keys($responses));
        self::assertSame(ResponseOrigin::Declared, $responses[201]->origin);
    }

    public function test_empty_responses(): void
    {
        $destroy = $this->only('delete', 'api/articles/{article}', 'destroy');
        $this->context = null;
        $nothing = $this->only('get', 'api/nothing', 'nothing');

        self::assertSame([204, []], [$destroy->status, $destroy->contents]);
        self::assertSame([200, []], [$nothing->status, $nothing->contents]);
    }

    public function test_a_redirect_has_its_location(): void
    {
        $response = $this->only('get', 'api/redirect', 'redirect');

        self::assertSame(302, $response->status);
        self::assertSame(['Location'], array_map(static fn(HeaderDescription $header): string => $header->name, $response->headers));
    }

    public function test_files_and_streams(): void
    {
        $download = $this->only('get', 'api/download', 'download');
        $this->context = null;
        $stream = $this->only('get', 'api/stream', 'stream');
        $this->context = null;
        $json = $this->only('get', 'api/stream-json', 'streamJson');
        $this->context = null;
        $events = $this->only('get', 'api/events', 'events');

        Schemas::assertSame('{"type":"string","format":"binary"}', $download->contents['application/octet-stream'] ?? Schema::create());
        self::assertSame(['Content-Disposition'], array_map(static fn(HeaderDescription $header): string => $header->name, $download->headers));
        Schemas::assertSame('{"type":"string","format":"binary"}', $stream->contents['application/octet-stream'] ?? Schema::create());
        Schemas::assertSame('{}', $json->contents['application/json'] ?? Schema::string());
        self::assertSame([], $events->contents);
        Schemas::assertSame('{"type":"object","properties":{"event":{"type":"string"},"data":{"type":"string"}},"required":["event","data"],"additionalProperties":false}', $events->itemContents['text/event-stream'] ?? Schema::create());
    }

    public function test_a_responsable_is_run(): void
    {
        $response = $this->only('get', 'api/responsable', 'responsable');

        self::assertSame(202, $response->status);
        Schemas::assertSame('{"type":"object","properties":{"ok":{"type":"boolean"}},"required":["ok"]}', $this->jsonSchema($response));
        self::assertSame(['X-Trace'], array_map(static fn(HeaderDescription $header): string => $header->name, $response->headers));
    }

    public function test_a_view_is_not_documented(): void
    {
        self::assertSame([], $this->responses('get', 'api/view', 'view'));
        self::assertDiagnostics(['[info] response GET /api/view: views are not documented'], $this->ctx());
    }

    public function test_no_return_type_gives_no_response(): void
    {
        $this->router()->get('api/untyped', [Signatures::class, 'nothing']);

        self::assertSame([], $this->describe());
        self::assertDiagnostics(['[info] response GET /api/untyped: no return type, declare the responses with #[Response]'], $this->ctx());
    }

    public function test_resources_of_one_status_are_one_of_them_with_a_discriminator(): void
    {
        Schemas::assertSame(
            '{"type":"object","properties":{"data":{"oneOf":[{"$ref":"#/components/schemas/Cat"},{"$ref":"#/components/schemas/Dog"}],"discriminator":{"propertyName":"type","mapping":{"cat":"#/components/schemas/Cat","dog":"#/components/schemas/Dog"}}}},"required":["data"]}',
            $this->jsonSchema($this->only('get', 'api/pet', 'pet')),
        );
    }

    public function test_declared_responses_override_by_status(): void
    {
        $responses = $this->responses('get', 'api/declared', 'declared');

        self::assertSame([200, 201], array_keys($responses));
        self::assertSame('The article', $responses[200]->description);
        self::assertSame(ResponseOrigin::Declared, $responses[200]->origin);
        Schemas::assertSame('{"type":"object","properties":{"data":{"$ref":"#/components/schemas/Article"},"meta":{"type":"object","properties":{"version":{"type":"string"}},"required":["version"]}},"required":["data","meta"]}', $this->jsonSchema($responses[200]));
        self::assertSame(['Author created', 'Done'], [$responses[201]->description, $responses[201]->summary]);
        Schemas::assertSame('{"type":"object","properties":{"data":{"$ref":"#/components/schemas/Author"}},"required":["data"],"example":{"id":1}}', $this->jsonSchema($responses[201]));
        self::assertSame(['X-Id'], array_map(static fn(HeaderDescription $header): string => $header->name, $responses[201]->headers));
        Schemas::assertSame('{"type":"integer"}', $responses[201]->headers[0]->schema);
    }

    public function test_declared_exceptions_add_their_responses(): void
    {
        $this->context = $this->ctx(exceptions: new ExceptionsConfiguration(map: [PaymentRequiredException::class => ['status' => 402, 'description' => 'Pay first']]));

        $responses = array_map(
            static fn(ResponseDescription $response): array => [$response->status, $response->description, $response->origin],
            $this->describe('get', 'api/throwing', 'throwing'),
        );

        self::assertSame([
            [200, 'OK', ResponseOrigin::Inferred],
            [404, 'Not Found (ModelNotFoundException)', ResponseOrigin::Exception],
            [403, 'Forbidden (AuthorizationException)', ResponseOrigin::Exception],
            [422, 'Unprocessable Content (ValidationException)', ResponseOrigin::Exception],
            [409, 'Conflict (ConflictHttpException)', ResponseOrigin::Exception],
            [409, 'Seat already taken (ChildSeatException)', ResponseOrigin::Exception],
            [402, 'Pay first (PaymentRequiredException)', ResponseOrigin::Exception],
            [418, "I'm a teapot (TeapotException)", ResponseOrigin::Exception],
        ], $responses);
        self::assertDiagnostics(['[info] response GET /api/throwing: no response known for ' . UnknownException::class], $this->ctx());
        Schemas::assertSame('{"type":"object","properties":{"message":{"type":"string"}},"required":["message"]}', $this->componentSchema('Error'));
        Schemas::assertSame('{"type":"object","properties":{"message":{"type":"string"},"errors":{"type":"object","additionalProperties":{"type":"array","items":{"type":"string"}}}},"required":["message","errors"]}', $this->componentSchema('ValidationError'));
    }

    public function test_a_second_document_gets_the_whole_schema_of_a_resource(): void
    {
        $this->router()->get('api/articles/{article}', [ResponseController::class, 'show']);
        $types = new TypeResolver();
        $session = $this->samplingSession();
        $responses = OperationResponses::default(Samplers::default($session, $types), $types);

        foreach ([1, 2] as $document) {
            $context = $this->context(sampling: new SamplingConfiguration(examples: false));
            $operation = (new OperationContextFactory($types))->create($this->definitions($context)[0], $context);
            $session->run(static fn(): array => $responses->describe($operation, $context), $context);

            Schemas::assertSame(self::ARTICLE, $context->components->get('Article') ?? Schema::create(), "document {$document}");
        }
    }

    public function test_an_unwrapped_resource_with_additional_information_is_put_under_data(): void
    {
        Schemas::assertSame('{"type":"object","properties":{"data":{"$ref":"#/components/schemas/Unwrapped"},"meta":{"type":"object","properties":{"version":{"type":"string"}},"required":["version"]}},"required":["data","meta"]}', $this->jsonSchema($this->only('get', 'api/unwrapped', 'unwrapped')));
    }

    public function test_a_resource_whose_data_holds_data_is_not_wrapped_again(): void
    {
        Schemas::assertSame('{"$ref":"#/components/schemas/PreWrapped"}', $this->jsonSchema($this->only('get', 'api/pre-wrapped', 'preWrapped')));
    }

    public function test_the_discriminator_is_a_property_whose_value_does_not_change_between_samples(): void
    {
        $data = self::at($this->jsonSchema($this->only('get', 'api/animal', 'animal')), 'data');

        self::assertSame('type', $data->discriminator?->propertyName);
    }

    public function test_a_failing_run_with_relations_keeps_the_plain_schema(): void
    {
        $this->only('get', 'api/broken-author', 'brokenAuthor');

        Schemas::assertSame('{"type":"object","properties":{"id":{"type":"integer"},"author":{}},"required":["id","author"]}', $this->componentSchema('ArticleWithBrokenAuthor'));
        self::assertStringContainsString('broken author', implode("\n", array_map(strval(...), $this->ctx()->diagnostics->all())));
    }

    public function test_a_nested_resource_is_described_with_its_relations(): void
    {
        $this->only('get', 'api/wrapper', 'wrapper');

        Schemas::assertSame(self::ARTICLE, $this->componentSchema('Article'));
    }

    public function test_a_paginator_without_type_arguments_has_the_shape_of_laravel(): void
    {
        $plain = $this->jsonSchema($this->only('get', 'api/plain-paginator', 'plainPaginator'));
        $this->context = null;
        $contract = $this->jsonSchema($this->only('get', 'api/contract-paginator', 'contractPaginator'));

        self::assertContains('total', self::keys($plain));
        self::assertContains('total', self::keys($contract));
    }

    public function test_a_body_declared_by_a_parent_is_kept_when_the_child_declares_a_description(): void
    {
        $this->router()->get('api/inherited', [ChildResponseController::class, 'inherited']);

        $response = $this->describe()[0];

        self::assertSame('Fine', $response->description);
        Schemas::assertSame('{"type":"object","properties":{"data":{"$ref":"#/components/schemas/Author"}},"required":["data"]}', $this->jsonSchema($response));
    }

    public function test_two_bodies_declared_for_one_status_are_one_of_them(): void
    {
        $this->router()->get('api/two-bodies', [ChildResponseController::class, 'twoBodies']);

        $responses = $this->describe();

        self::assertCount(1, $responses);
        Schemas::assertSame([
            'oneOf' => [['$ref' => '#/components/schemas/Cat'], ['$ref' => '#/components/schemas/Dog']],
            'discriminator' => ['propertyName' => 'type', 'mapping' => ['cat' => '#/components/schemas/Cat', 'dog' => '#/components/schemas/Dog']],
        ], self::at($this->jsonSchema($responses[0]), 'data'));
    }

    public function test_a_422_that_is_not_a_validation_has_the_error_body_and_an_exception_attribute_is_complete(): void
    {
        $responses = $this->describe('get', 'api/unprocessable', 'unprocessable');

        Schemas::assertSame('{"$ref":"#/components/schemas/Error"}', $this->jsonSchema($responses[1]));
        self::assertSame([409, 'Busy'], [$responses[2]->status, $responses[2]->summary]);
        Schemas::assertSame('{"type":"object","properties":{"data":{"$ref":"#/components/schemas/Author"}},"required":["data"]}', $this->jsonSchema($responses[2]));
        self::assertSame(['Retry-After'], array_map(static fn(HeaderDescription $header): string => $header->name, $responses[2]->headers));
    }

    /**
     * @return array<int|string, ResponseDescription>
     */
    private function responses(string $method, string $uri, string $action): array
    {
        $keyed = [];

        foreach ($this->describe($method, $uri, $action) as $response) {
            $keyed[$response->status] = $response;
        }

        ksort($keyed);

        return $keyed;
    }

    private function only(string $method, string $uri, string $action, ?GenerationContext $context = null): ResponseDescription
    {
        $this->context = $context ?? $this->context;
        $responses = $this->describe($method, $uri, $action);
        self::assertCount(1, $responses, implode("\n", array_map(strval(...), $this->ctx()->diagnostics->all())));

        return $responses[0];
    }

    /**
     * @return list<ResponseDescription>
     */
    private function describe(?string $method = null, ?string $uri = null, ?string $action = null): array
    {
        if ($method !== null && $uri !== null && $action !== null) {
            $this->router()->match([strtoupper($method)], $uri, [ResponseController::class, $action]);
        }

        $context = $this->ctx();
        $definitions = $this->definitions($context);
        self::assertNotEmpty($definitions);
        // The route registered last.
        $operation = (new OperationContextFactory(new TypeResolver()))->create($definitions[array_key_last($definitions)], $context);
        $types = new TypeResolver();
        $session = $this->samplingSession();
        $responses = OperationResponses::default(Samplers::default($session, $types), $types);

        return $session->run(static fn(): array => $responses->describe($operation, $context), $context);
    }

    private function ctx(bool $useReferences = true, ExceptionsConfiguration $exceptions = new ExceptionsConfiguration()): GenerationContext
    {
        return $this->context ??= $this->context(exceptions: $exceptions, sampling: new SamplingConfiguration(examples: false), useReferences: $useReferences);
    }

    private function jsonSchema(ResponseDescription $response): Schema
    {
        return $response->contents['application/json'] ?? Schema::create();
    }

    /**
     * The schema at a path of properties, `*` for the items of a list.
     */
    private static function at(Schema $schema, string ...$path): Schema
    {
        foreach ($path as $segment) {
            $next = $segment === '*' ? $schema->items : ($schema->properties[$segment] ?? null);
            self::assertInstanceOf(Schema::class, $next, $segment);
            $schema = $next;
        }

        return $schema;
    }

    /**
     * @return list<string>
     */
    private static function keys(Schema $schema): array
    {
        return array_keys($schema->properties ?? []);
    }

    private function componentSchema(string $name): Schema
    {
        return $this->ctx()->components->get($name) ?? Schema::create();
    }
}
