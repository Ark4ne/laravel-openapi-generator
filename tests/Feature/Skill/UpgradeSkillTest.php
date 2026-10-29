<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Feature\Skill;

use Ark4ne\OpenApi\Tests\Support\Schemas;
use Illuminate\Translation\Translator;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * The upgrade skill shipped with the package (`skills/laravel-openapi-generator-upgrade/`): every 1.0 port of its
 * examples runs in an application and gives a valid document in 3.0, 3.1 and 3.2, the files link to each other, and
 * the mapping names everything 0.6 exposed to an application.
 */
#[CoversNothing]
final class UpgradeSkillTest extends SkillTestCase
{
    /**
     * The configuration keys, PHPDoc tags, attribute arguments and classes of 0.6 an application could use.
     */
    private const LEGACY = [
        'output-disk', 'output-dir', 'variable', 'versions', 'output-file', 'title', 'description', 'routes', 'name-by',
        'tag-by', 'group-by', 'regex', 'ignore-verbs', 'parameters.query.flat', 'use-ref', 'parsers.requests',
        'parsers.rules', 'parsers.responses', 'middlewares', 'format.date', 'connections.use-transaction',
        'connections.on-connections', 'languages',
        '@oa-description', '@oa-response-status', '@oa-response-paginate', '@oa-response-header',
        'statusCode', 'statusText', 'headers', 'paginated', '#[Id(', '#[ResourceFactory(', '#[Request(',
        'DescribableRequest', 'Describer', 'CustomRuleParserContract', 'CustomRuleParser', 'RequestParserContract',
        'ResponseParserContract', 'Transformer', 'ApplyBearerTokenSecurity', 'ApplyXsrfSecurity', 'ApplyCsrfSecurity',
        'IncludesRuleParsers', 'FieldsRuleParsers', 'DescribedRequestParser', 'FORMAT_DATE', 'FORMAT_DATETIME',
        'LaravelOpenApiProvider',
        'openapi.requests.<route name>.name', 'openapi.requests.<route name>.description',
        'openapi.requests.descriptions.<key>', 'openapi.requests.parameters.<field>',
        'openapi.requests.parameters.custom.<route name>.<field>',
    ];

    protected static function skill(): string
    {
        return 'laravel-openapi-generator-upgrade';
    }

    protected static function files(): array
    {
        return ['SKILL.md', 'mapping.md', 'examples.md'];
    }

    public function test_the_mapping_names_everything_an_application_used_from_0_6(): void
    {
        $mapping = self::read('mapping.md');

        foreach (self::LEGACY as $legacy) {
            self::assertStringContainsString($legacy, $mapping, $legacy);
        }
    }

    public function test_the_tags_and_attributes_example(): void
    {
        $document = $this->example('examples.md', 'PHPDoc tags and response attributes');

        self::assertSame(['data', 'links', 'meta'], array_keys((array) Schemas::at($document, 'paths', '/api/orders', 'get', 'responses', '200', 'content', 'application/json', 'schema', 'properties')));
        self::assertSame([201, 422], array_keys((array) Schemas::at($document, 'paths', '/api/orders', 'post', 'responses')));
        self::assertSame(['Location'], array_keys((array) Schemas::at($document, 'paths', '/api/orders', 'post', 'responses', '201', 'headers')));
        self::assertSame('#/components/schemas/Order', Schemas::at($document, 'paths', '/api/orders', 'post', 'responses', '201', 'content', 'application/json', 'schema', 'properties', 'data', '$ref'));
        self::assertSame([202], array_keys((array) Schemas::at($document, 'paths', '/api/orders/export', 'post', 'responses')));
        self::assertSame('Export queued', Schemas::at($document, 'paths', '/api/orders/export', 'post', 'responses', '202', 'description'));
        self::assertSame('integer', Schemas::at($document, 'paths', '/api/orders/export', 'post', 'responses', '202', 'headers', 'Retry-After', 'schema', 'type'));
    }

    public function test_the_described_requests_example(): void
    {
        $operation = Schemas::at($this->example('examples.md', 'Described requests'), 'paths', '/api/imports', 'post');
        self::assertIsArray($operation);
        $parameters = self::parameters($operation);

        self::assertSame(['X-Tenant', 'Content-Encoding', 'dry_run'], array_keys($parameters));
        self::assertTrue($parameters['X-Tenant']['required'] ?? null);
        self::assertSame('Validate without importing', $parameters['dry_run']['description'] ?? null);
        self::assertSame(['file'], array_keys((array) Schemas::at($operation, 'requestBody', 'content', 'multipart/form-data', 'schema', 'properties')));
        self::assertSame([['sanctum' => []], ['session' => [], 'xsrf' => []]], $operation['security'] ?? null);
    }

    public function test_the_custom_rule_parsers_example(): void
    {
        $slug = Schemas::at($this->example('examples.md', 'Custom rule parsers'), 'paths', '/api/pages', 'post', 'requestBody', 'content', 'application/json', 'schema', 'properties', 'slug');

        self::assertSame(['description' => 'A slug', 'type' => 'string', 'pattern' => '^[a-z0-9-]+$'], $slug);
    }

    public function test_the_middleware_transformers_example(): void
    {
        $document = $this->example('examples.md', 'Middleware transformers', ['middlewares' => ['api.key' => ['App\ApiKeys\ApiKeyMiddlewareMapper']]]);

        self::assertSame([['apiKey' => []]], Schemas::at($document, 'paths', '/api/partner/status', 'get', 'security'));
        self::assertSame(['type' => 'apiKey', 'name' => 'X-Api-Key', 'in' => 'header'], Schemas::at($document, 'components', 'securitySchemes', 'apiKey'));
    }

    public function test_the_translation_keys_example(): void
    {
        $translator = $this->app?->make('translator');
        self::assertInstanceOf(Translator::class, $translator);
        $translator->addLines([
            'openapi.requests.invoices.index.name' => 'List the invoices',
            'openapi.requests.invoices.index.description' => 'Every invoice of the account.',
            'openapi.requests.parameters.status' => 'Status of the invoice',
        ], 'en');

        $operation = Schemas::at($this->example('examples.md', 'Translation keys of 0.6'), 'paths', '/api/invoices', 'get');
        self::assertIsArray($operation);

        self::assertSame('List the invoices', $operation['summary'] ?? null);
        self::assertSame('Every invoice of the account.', $operation['description'] ?? null);
        self::assertSame('Status of the invoice', self::parameters($operation)['status']['description'] ?? null);
    }

    /**
     * The parameters of an operation, by name.
     *
     * @param array<array-key, mixed> $operation
     *
     * @return array<string, array<array-key, mixed>>
     */
    private static function parameters(array $operation): array
    {
        $parameters = [];

        foreach ((array) Schemas::at($operation, 'parameters') as $parameter) {
            self::assertIsArray($parameter);
            $name = $parameter['name'] ?? null;
            self::assertIsString($name);
            $parameters[$name] = $parameter;
        }

        return $parameters;
    }
}
