<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Integrations\JsonApi;

use Ark4ne\OpenApi\Integrations\BuiltInIntegrations;
use Ark4ne\OpenApi\Integrations\JsonApi\FieldsRuleMapper;
use Ark4ne\OpenApi\Integrations\JsonApi\IncludesRuleMapper;
use Ark4ne\OpenApi\Integrations\JsonApi\JsonApiErrorBodies;
use Ark4ne\OpenApi\Integrations\JsonApi\JsonApiIntegration;
use Ark4ne\OpenApi\Integrations\JsonApi\JsonApiResourceFormat;
use Ark4ne\OpenApi\Tests\Support\Schemas;
use Ark4ne\OpenApi\Tests\TestCase;
use Ark4ne\OpenApiWriter\Objects\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(JsonApiIntegration::class)]
#[CoversClass(JsonApiErrorBodies::class)]
#[CoversClass(BuiltInIntegrations::class)]
#[Group('json-api')]
final class JsonApiIntegrationTest extends TestCase
{
    public function test_it_is_available_when_the_package_is_installed_and_registers_its_classes(): void
    {
        $integration = new JsonApiIntegration();

        self::assertTrue($integration->available());
        self::assertEquals([new JsonApiResourceFormat()], $integration->resourceFormats());
        self::assertEquals([new IncludesRuleMapper(), new FieldsRuleMapper()], $integration->ruleMappers());
    }

    public function test_the_json_api_errors_are_opt_in(): void
    {
        self::assertNull((new JsonApiIntegration())->errorBodies());
        self::assertInstanceOf(JsonApiErrorBodies::class, (new JsonApiIntegration(errors: true))->errorBodies());
    }

    public function test_the_built_in_integrations_are_listed_with_their_options(): void
    {
        self::assertEquals([new JsonApiIntegration()], BuiltInIntegrations::all());
        self::assertEquals([new JsonApiIntegration(errors: true)], BuiltInIntegrations::all(jsonApiErrors: true));
    }

    public function test_the_error_bodies_are_json_api_error_documents(): void
    {
        $context = $this->context();
        $errors = new JsonApiErrorBodies();

        self::assertSame('application/vnd.api+json', $errors->mediaType());
        Schemas::assertSame(['$ref' => '#/components/schemas/JsonApiErrors'], $errors->error($context));
        Schemas::assertSame(['$ref' => '#/components/schemas/JsonApiErrors'], $errors->validation($context));
        Schemas::assertSame([
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'string'],
                'status' => ['type' => 'string'],
                'code' => ['type' => 'string'],
                'title' => ['type' => 'string'],
                'detail' => ['type' => 'string'],
                'source' => ['type' => 'object', 'properties' => ['pointer' => ['type' => 'string'], 'parameter' => ['type' => 'string'], 'header' => ['type' => 'string']]],
                'meta' => ['type' => 'object'],
            ],
        ], self::items($errors->error($this->context(useReferences: false))));
        self::assertSame(['errors'], $context->components->get('JsonApiErrors')?->required);
    }

    private static function items(Schema $errors): Schema
    {
        $list = $errors->properties['errors'] ?? null;
        self::assertInstanceOf(Schema::class, $list);
        self::assertInstanceOf(Schema::class, $list->items);

        return $list->items;
    }

    public function test_the_error_bodies_are_written_inline_without_references(): void
    {
        $context = $this->context(useReferences: false);

        self::assertSame(['errors'], array_keys((new JsonApiErrorBodies())->error($context)->properties ?? []));
    }
}
