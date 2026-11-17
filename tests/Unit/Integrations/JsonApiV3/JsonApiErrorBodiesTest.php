<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Integrations\JsonApiV3;

use Ark4ne\JsonApi\Configuration\JsonApiObject;
use Ark4ne\OpenApi\Integrations\JsonApiV3\JsonApiErrorBodies;
use Ark4ne\OpenApi\Integrations\JsonApiV3\JsonApiObjects;
use Ark4ne\OpenApi\Integrations\JsonApiV3\ResourceDefinitions;
use Ark4ne\OpenApi\Tests\Support\Schemas;
use Ark4ne\OpenApiWriter\Objects\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(JsonApiErrorBodies::class)]
#[CoversClass(JsonApiObjects::class)]
#[Group('json-api-v3')]
final class JsonApiErrorBodiesTest extends JsonApiV3TestCase
{
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
