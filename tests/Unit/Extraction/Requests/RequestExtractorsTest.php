<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Extraction\Requests;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\ParameterDescription;
use Ark4ne\OpenApi\Description\RequestDescription;
use Ark4ne\OpenApi\Extraction\Requests\AttributeRequestExtractor;
use Ark4ne\OpenApi\Extraction\Requests\DescribableRequestExtractor;
use Ark4ne\OpenApi\Extraction\Requests\FieldPlacer;
use Ark4ne\OpenApi\Extraction\Requests\FormRequestExtractor;
use Ark4ne\OpenApi\Extraction\Requests\PathParameterRequestExtractor;
use Ark4ne\OpenApi\Extraction\Requests\RequestDescriber;
use Ark4ne\OpenApi\Extraction\Requests\RequestExtractors;
use Ark4ne\OpenApi\Extraction\Requests\RequestFactory;
use Ark4ne\OpenApi\Extraction\Responses\ResourceSchemas;
use Ark4ne\OpenApi\Extraction\Strategies\BuiltInQueryStrategy;
use Ark4ne\OpenApi\Sampling\ModelSchemas;
use Ark4ne\OpenApi\Sampling\NullSamplingSession;
use Ark4ne\OpenApi\Sampling\RelationLoader;
use Ark4ne\OpenApi\Sampling\ResourceModels;
use Ark4ne\OpenApi\Sampling\Samplers;
use Ark4ne\OpenApi\Sampling\SchemaInferrer;
use Ark4ne\OpenApi\Tests\Fixtures\Integrations\TestResourceFormat;
use Ark4ne\OpenApi\Tests\Fixtures\Requests\ChildRequestController;
use Ark4ne\OpenApi\Tests\Fixtures\Requests\FailingRequest;
use Ark4ne\OpenApi\Tests\Fixtures\Requests\RequestController;
use Ark4ne\OpenApi\Tests\Fixtures\Requests\ThrowingRuleRequest;
use Ark4ne\OpenApi\Tests\Support\Schemas;
use Ark4ne\OpenApi\Tests\TestCase;
use Ark4ne\OpenApi\Types\TypeResolver;
use Ark4ne\OpenApiWriter\Enums\ParameterLocation;
use Ark4ne\OpenApiWriter\Enums\Version;
use Ark4ne\OpenApiWriter\Objects\Schema;
use Illuminate\Contracts\Container\Container;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(RequestExtractors::class)]
#[CoversClass(PathParameterRequestExtractor::class)]
#[CoversClass(FormRequestExtractor::class)]
#[CoversClass(DescribableRequestExtractor::class)]
#[CoversClass(AttributeRequestExtractor::class)]
#[CoversClass(RequestDescriber::class)]
#[CoversClass(RequestFactory::class)]
#[CoversClass(FieldPlacer::class)]
final class RequestExtractorsTest extends TestCase
{
    public function test_the_extractors_run_by_increasing_priority(): void
    {
        $extractors = RequestExtractors::default($this->container(), new BuiltInQueryStrategy('all'));

        self::assertSame(
            [PathParameterRequestExtractor::class, FormRequestExtractor::class, DescribableRequestExtractor::class, AttributeRequestExtractor::class],
            array_map(get_class(...), $extractors->all()),
        );
    }

    public function test_the_path_parameters_come_from_the_route(): void
    {
        $this->router()->get('api/posts/{post}', [RequestController::class, 'plain'])->whereNumber('post');

        $request = $this->request();

        self::assertSame(['post'], self::names($request->parameters));
        Schemas::assertSame(['type' => 'integer'], $request->parameters[0]->schema);
        self::assertNull($request->body);
    }

    public function test_a_form_request_gives_a_json_body(): void
    {
        $this->router()->post('api/users', [RequestController::class, 'store']);

        $request = $this->request();

        self::assertTrue($request->validated);
        self::assertTrue($request->authorized);
        self::assertSame(['application/json'], array_keys($request->body->contents ?? []));
        self::assertTrue($request->body?->required);
        Schemas::assertSame('{"type":"object","properties":{"name":{"type":"string","maxLength":255},"email":{"type":"string","format":"email","description":"Unique in users.email."},"status":{"type":"string","enum":["draft","published"]},"tags":{"type":"array","items":{"type":"string"},"maxItems":5,"uniqueItems":true}},"required":["name","email"]}', $request->body?->contents['application/json'] ?? Schema::create());
    }

    public function test_a_form_request_without_authorize_is_not_authorized(): void
    {
        $this->router()->post('api/avatars', [RequestController::class, 'upload']);

        self::assertFalse($this->request()->authorized);
    }

    public function test_a_file_makes_the_body_multipart_with_its_encodings(): void
    {
        $this->router()->post('api/avatars', [RequestController::class, 'upload']);

        $body = $this->request()->body;

        self::assertSame(['multipart/form-data'], array_keys($body->contents ?? []));
        self::assertSame(
            ['multipart/form-data' => ['avatar' => 'image/jpeg, image/png, image/bmp, image/gif, image/webp', 'photos' => 'image/jpeg, image/png']],
            $body?->encodings,
        );
        Schemas::assertSame('{"type":"object","properties":{"avatar":{"type":"string","format":"binary","description":"At most 1024 KB."},"photos":{"type":"array","items":{"type":"string","format":"binary"}},"caption":{"type":["string","null"]}},"required":["avatar"]}', $body?->contents['multipart/form-data'] ?? Schema::create());
    }

    public function test_a_get_request_puts_its_fields_in_the_query(): void
    {
        $this->router()->get('api/search', [RequestController::class, 'search']);

        $request = $this->request();

        self::assertNull($request->body);
        self::assertSame(['q', 'page', 'filter[][name]'], self::names($request->parameters));
        self::assertSame(ParameterLocation::Query, $request->parameters[0]->in);
        self::assertTrue($request->parameters[0]->required);
        Schemas::assertSame(['type' => 'integer', 'minimum' => 1], $request->parameters[1]->schema);
    }

    public function test_the_rules_get_a_bound_route_and_their_dependencies(): void
    {
        $this->router()->put('api/posts/{post}', [RequestController::class, 'update']);
        $context = $this->context();

        $request = $this->request($context);

        self::assertDiagnostics([], $context);
        self::assertSame(['post'], self::names($request->parameters));
        Schemas::assertSame('{"type":"object","properties":{"slug":{"type":"string","description":"Unique in posts.slug."},"title":{"type":"string","maxLength":100}}}', $request->body?->contents['application/json'] ?? Schema::create());
    }

    public function test_rules_that_fail_give_a_warning(): void
    {
        $this->router()->post('api/failing', [RequestController::class, 'failing']);
        $context = $this->context();

        $request = $this->request($context);

        self::assertNull($request->body);
        self::assertDiagnostics(['[warning] request POST /api/failing: cannot read the rules of ' . FailingRequest::class . ': database unavailable'], $context);
    }

    public function test_a_manual_description_completes_the_rules(): void
    {
        $this->router()->post('api/described', [RequestController::class, 'described']);

        $request = $this->request();

        self::assertSame(['X-Tenant', 'locale', 'include'], self::names($request->parameters));
        self::assertSame([ParameterLocation::Header, ParameterLocation::Cookie, ParameterLocation::Query], array_map(static fn(ParameterDescription $p): ParameterLocation => $p->in, $request->parameters));
        self::assertTrue($request->parameters[0]->required);
        self::assertSame('Tenant of the request', $request->parameters[0]->description);
        Schemas::assertSame(['type' => 'string', 'format' => 'uuid'], $request->parameters[0]->schema);
        Schemas::assertSame(['type' => 'string', 'enum' => ['posts', 'comments']], $request->parameters[2]->schema);
        self::assertSame(['application/json', 'application/xml', 'text/plain'], array_keys($request->body->contents ?? []));
        Schemas::assertSame('{"type":"object","properties":{"name":{"type":"string"},"payload":{"type":"array"}},"required":["name"]}', $request->body?->contents['application/xml'] ?? Schema::create());
        Schemas::assertSame('{"type":"object","properties":{"name":{"type":"string"},"extra":{"type":"string"}},"required":["name"]}', $request->body?->contents['application/json'] ?? Schema::create());
        Schemas::assertSame('{"type":"object","properties":{"raw":{"type":"string"}}}', $request->body?->contents['text/plain'] ?? Schema::create());
        self::assertSame('The user to store', $request->body?->description);
    }

    public function test_the_attributes_of_the_request_and_of_the_action_add_parameters(): void
    {
        $this->router()->get('api/attributed', [RequestController::class, 'attributed']);

        $request = $this->request();

        self::assertSame(['locale', 'X-Api-Version', 'session'], self::names($request->parameters));
        self::assertSame('Overridden by the action', $request->parameters[0]->description);
        self::assertTrue($request->parameters[0]->deprecated);
        Schemas::assertSame(['type' => 'string'], $request->parameters[0]->schema);
        Schemas::assertSame(['type' => 'integer', 'example' => 2], $request->parameters[1]->schema);
        self::assertTrue($request->parameters[1]->required);
        self::assertSame(ParameterLocation::Cookie, $request->parameters[2]->in);
        Schemas::assertSame(['type' => 'string'], $request->parameters[2]->schema);
    }

    public function test_path_parameter_and_request_body_attributes(): void
    {
        $this->router()->patch('api/posts/{post}', [RequestController::class, 'patch']);

        $request = $this->request();

        Schemas::assertSame(['type' => 'integer', 'minimum' => 1, 'example' => 1], $request->parameters[0]->schema);
        self::assertSame('Identifier of the post', $request->parameters[0]->description);
        Schemas::assertSame('{"type":"object","properties":{"title":{"type":"string"},"tags":{"type":"array","items":{"type":"string"}}},"required":["title"],"additionalProperties":false}', $request->body?->contents['application/json'] ?? Schema::create());
        self::assertSame('The changes', $request->body?->description);
        self::assertTrue($request->body->required);
    }

    public function test_a_resource_body_is_the_schema_of_the_resource(): void
    {
        $this->router()->post('api/resource', [RequestController::class, 'resource']);
        $context = $this->context();
        $operation = $this->operation($context);
        $types = new TypeResolver();
        $resources = new ResourceSchemas(new ResourceModels($types), Samplers::default(new NullSamplingSession(), $types), new SchemaInferrer(new ModelSchemas($types)), new RelationLoader(Samplers::default(new NullSamplingSession(), $types)));

        $request = RequestExtractors::default($this->container(), new BuiltInQueryStrategy('last'), resources: $resources)->extract($operation, RequestDescription::create(), $context);

        Schemas::assertSame('{"$ref":"#/components/schemas/Author"}', $request->body?->contents['application/json'] ?? Schema::create());
        self::assertTrue($context->components->has('Author'));
    }

    public function test_a_resource_body_of_a_format_is_its_request_in_its_media_type(): void
    {
        $this->router()->post('api/resource', [RequestController::class, 'resource']);
        $context = $this->context();
        $operation = $this->operation($context);
        $types = new TypeResolver();
        $samplers = Samplers::default(new NullSamplingSession(), $types);
        $resources = new ResourceSchemas(new ResourceModels($types), $samplers, new SchemaInferrer(new ModelSchemas($types)), new RelationLoader($samplers), [new TestResourceFormat()]);

        $request = RequestExtractors::default($this->container(), new BuiltInQueryStrategy('last'), resources: $resources)->extract($operation, RequestDescription::create(), $context);

        Schemas::assertSame(['type' => 'object', 'properties' => ['request' => ['$ref' => '#/components/schemas/Author']], 'required' => ['request']], $request->body?->contents['application/x-test'] ?? Schema::create());
    }

    public function test_application_code_that_fails_while_reading_the_rules_gives_a_warning(): void
    {
        $this->router()->post('api/throwing', [RequestController::class, 'throwing']);
        $context = $this->context();

        $request = $this->request($context);

        self::assertNull($request->body);
        self::assertDiagnostics(['[warning] request POST /api/throwing: cannot read the rules of ' . ThrowingRuleRequest::class . ': cannot describe'], $context);
    }

    public function test_a_manual_description_without_rules_is_not_validated(): void
    {
        $this->router()->get('api/described', [RequestController::class, 'describedPlain']);

        $request = $this->request();

        self::assertSame(['include'], self::names($request->parameters));
        self::assertFalse($request->validated);
    }

    public function test_querystring_takes_the_query_parameters_of_the_attributes(): void
    {
        $this->router()->get('api/search', [RequestController::class, 'localized']);
        $context = $this->context(version: Version::V3_2_0);
        $operation = $this->operation($context);

        $request = RequestExtractors::default($this->container(), new BuiltInQueryStrategy('querystring'))->extract($operation, RequestDescription::create(), $context);

        self::assertSame(['query'], self::names($request->parameters));
        self::assertSame(ParameterLocation::Querystring, $request->parameters[0]->in);
        Schemas::assertSame('{"type":"object","properties":{"q":{"type":"string"},"page":{"type":"integer","minimum":1},"filter":{"type":"array","items":{"type":"object","properties":{"name":{"type":"string"}}}},"locale":{"type":"string"}},"required":["q"]}', $request->parameters[0]->schema);
    }

    public function test_a_request_body_without_type_keeps_the_body_of_the_rules(): void
    {
        $this->router()->post('api/users', [RequestController::class, 'documented']);

        $body = $this->request()->body;
        self::assertNotNull($body);

        self::assertSame('The user', $body->description);
        self::assertTrue($body->required);
        self::assertSame(['name', 'email', 'status', 'tags'], array_keys(($body->contents['application/json'] ?? Schema::create())->properties ?? []));
    }

    public function test_the_type_of_an_inherited_attribute_is_read_in_the_scope_of_its_declaration(): void
    {
        $this->router()->get('api/filtered', [ChildRequestController::class, 'filtered']);

        Schemas::assertSame(['type' => 'string', 'enum' => ['draft', 'published']], $this->request()->parameters[0]->schema);
    }

    public function test_a_plain_request_has_no_body(): void
    {
        $this->router()->post('api/plain', [RequestController::class, 'plain']);

        $request = $this->request();

        self::assertNull($request->body);
        self::assertFalse($request->validated);
    }

    private function request(?GenerationContext $context = null): RequestDescription
    {
        $context ??= $this->context();
        $operation = $this->operation($context);

        return RequestExtractors::default($this->container(), new BuiltInQueryStrategy('last'))->extract($operation, RequestDescription::create(), $context);
    }

    private function container(): Container
    {
        $container = $this->app?->make(Container::class);
        self::assertInstanceOf(Container::class, $container);

        return $container;
    }

    /**
     * @param list<ParameterDescription> $parameters
     *
     * @return list<string>
     */
    private static function names(array $parameters): array
    {
        return array_map(static fn(ParameterDescription $parameter): string => $parameter->name, $parameters);
    }
}
