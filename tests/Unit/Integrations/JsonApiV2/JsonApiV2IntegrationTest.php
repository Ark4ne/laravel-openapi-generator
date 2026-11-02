<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Integrations\JsonApiV2;

use Ark4ne\JsonApi\Configuration\JsonApiObject;
use Ark4ne\OpenApi\Integrations\BuiltInIntegrations;
use Ark4ne\OpenApi\Integrations\JsonApi\JsonApiIntegration;
use Ark4ne\OpenApi\Integrations\JsonApiV2\FieldsRuleMapper;
use Ark4ne\OpenApi\Integrations\JsonApiV2\IncludesRuleMapper;
use Ark4ne\OpenApi\Integrations\JsonApiV2\JsonApiErrorBodies;
use Ark4ne\OpenApi\Integrations\JsonApiV2\JsonApiObjects;
use Ark4ne\OpenApi\Integrations\JsonApiV2\JsonApiResourceFormat;
use Ark4ne\OpenApi\Integrations\JsonApiV2\JsonApiV2Integration;
use Ark4ne\OpenApi\Integrations\JsonApiV2\ResourceDefinitions;
use Ark4ne\OpenApi\Tests\Support\Schemas;
use Ark4ne\OpenApi\Tests\TestCase;
use Ark4ne\OpenApiWriter\Objects\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(JsonApiV2Integration::class)]
#[CoversClass(JsonApiErrorBodies::class)]
#[CoversClass(JsonApiObjects::class)]
#[CoversClass(BuiltInIntegrations::class)]
#[Group('json-api-v2')]
final class JsonApiV2IntegrationTest extends TestCase
{
    public function test_it_is_available_when_the_package_is_installed_in_2_x_and_registers_its_classes(): void
    {
        $integration = new JsonApiV2Integration();

        self::assertTrue($integration->available());
        self::assertEquals([new JsonApiResourceFormat()], $integration->resourceFormats());
        self::assertEquals([new IncludesRuleMapper(), new FieldsRuleMapper()], $integration->ruleMappers());
    }

    public function test_the_1_x_integration_is_not_available_with_2_x(): void
    {
        self::assertFalse((new JsonApiIntegration())->available());
    }

    public function test_the_json_api_errors_are_opt_in(): void
    {
        self::assertNull((new JsonApiV2Integration())->errorBodies());
        self::assertInstanceOf(JsonApiErrorBodies::class, (new JsonApiV2Integration(errors: true))->errorBodies());
    }

    public function test_the_built_in_integrations_list_both_json_api_integrations_with_their_options(): void
    {
        self::assertEquals([new JsonApiIntegration(), new JsonApiV2Integration()], BuiltInIntegrations::all());
        self::assertEquals([new JsonApiIntegration(errors: true), new JsonApiV2Integration(errors: true)], BuiltInIntegrations::all(jsonApiErrors: true));
    }

    public function test_the_error_bodies_are_the_error_documents_of_the_package(): void
    {
        $context = $this->context();
        $errors = new JsonApiErrorBodies();

        self::assertSame('application/vnd.api+json', $errors->mediaType());
        Schemas::assertSame(['$ref' => '#/components/schemas/JsonApiErrors'], $errors->error($context));
        Schemas::assertSame(['$ref' => '#/components/schemas/JsonApiErrors'], $errors->validation($context));
        Schemas::assertSame([
            'type' => 'object',
            'properties' => [
                'jsonapi' => ['$ref' => '#/components/schemas/JsonApi'],
                'errors' => ['type' => 'array', 'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'links' => ['type' => 'object', 'properties' => ['about' => ['type' => 'string'], 'type' => ['type' => 'string']]],
                        'status' => ['type' => 'string'],
                        'code' => ['type' => 'string'],
                        'title' => ['type' => 'string'],
                        'detail' => ['type' => 'string'],
                        'source' => ['type' => 'object', 'properties' => ['pointer' => ['type' => 'string'], 'parameter' => ['type' => 'string'], 'header' => ['type' => 'string']]],
                        'meta' => ['type' => 'object'],
                    ],
                ]],
                'meta' => ['type' => 'object'],
            ],
            'required' => ['jsonapi', 'errors'],
        ], $context->components->get('JsonApiErrors') ?? Schema::create());
    }

    public function test_the_error_bodies_are_written_inline_without_references(): void
    {
        $context = $this->context(useReferences: false);
        $errors = (new JsonApiErrorBodies())->error($context);

        self::assertSame(['jsonapi', 'errors', 'meta'], array_keys($errors->properties ?? []));
        $jsonapi = $errors->properties['jsonapi'] ?? null;
        self::assertInstanceOf(Schema::class, $jsonapi);
        self::assertSame(['version'], array_keys($jsonapi->properties ?? []));
    }

    public function test_the_jsonapi_member_is_that_of_the_configuration(): void
    {
        $context = $this->context(useReferences: false);

        self::assertNull(JsonApiObjects::schema(null, $context));
        self::assertNotNull(ResourceDefinitions::jsonApiObject());
        Schemas::assertSame([
            'type' => 'object',
            'properties' => [
                'version' => ['type' => 'string', 'const' => '1.0'],
                'ext' => ['type' => 'array', 'items' => ['type' => 'string', 'format' => 'uri']],
                'profile' => ['type' => 'array', 'items' => ['type' => 'string', 'format' => 'uri']],
                'meta' => ['type' => 'object'],
            ],
            'required' => ['version', 'ext', 'profile', 'meta'],
        ], JsonApiObjects::schema(new JsonApiObject('1.0', ['https://example.com/ext'], ['https://example.com/profile'], ['copyright' => 'ACME']), $context) ?? Schema::create());
    }
}
