<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Feature\Skill;

use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Tests\Support\Schemas;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use ReflectionClass;

/**
 * The agent skill shipped with the package (`skills/laravel-openapi-generator/`): every recipe runs in an
 * application and gives a valid document in 3.0, 3.1 and 3.2, the files link to each other, and the reference names
 * every attribute, every configuration key and every extension point.
 */
#[CoversNothing]
final class SkillTest extends SkillTestCase
{
    protected static function skill(): string
    {
        return 'laravel-openapi-generator';
    }

    protected static function files(): array
    {
        return ['SKILL.md', 'reference.md', 'recipes.md'];
    }

    public function test_the_reference_names_every_attribute_and_its_parameters(): void
    {
        $reference = self::read('reference.md');

        $files = glob(dirname(__DIR__, 3) . '/src/Attributes/*.php');

        foreach ($files === false ? [] : $files as $file) {
            $class = 'Ark4ne\OpenApi\Attributes\\' . basename($file, '.php');
            self::assertTrue(class_exists($class), $class);
            self::assertStringContainsString('#[' . basename($file, '.php'), $reference, $class);

            foreach ((new ReflectionClass($class))->getConstructor()?->getParameters() ?? [] as $parameter) {
                self::assertMatchesRegularExpression('/`' . $parameter->name . '`/', self::section($reference, basename($file, '.php')), "{$class}::\${$parameter->name}");
            }
        }
    }

    public function test_the_reference_names_every_configuration_key_and_extension_point(): void
    {
        $reference = self::read('reference.md');

        foreach ([...Configuration::KEYS, 'openapi', 'strict', 'vendor_extensions', 'info', 'self', 'external_docs', 'routes', 'strategies', 'security_schemes', 'security', 'use_references', 'output'] as $key) {
            self::assertMatchesRegularExpression('/[`.]' . preg_quote($key, '/') . '`/', $reference, $key);
        }

        foreach (['OperationExtractor', 'RequestExtractor', 'RuleMapper', 'ResponseExtractor', 'Sampler', 'Pass', 'MiddlewareMapper', 'DescribesRequest', 'DescribesRule', 'SummaryStrategy', 'TagStrategy', 'GroupStrategy', 'OperationIdStrategy', 'QueryStrategy'] as $interface) {
            self::assertStringContainsString("`{$interface}`", $reference, $interface);
        }
    }

    public function test_the_crud_recipe(): void
    {
        $document = $this->recipe('CRUD resource');

        self::assertSame(['/api/books', '/api/books/{book}'], array_keys((array) Schemas::at($document, 'paths')));
        self::assertSame(['data', 'links', 'meta'], array_keys((array) Schemas::at($document, 'paths', '/api/books', 'get', 'responses', '200', 'content', 'application/json', 'schema', 'properties')));
        self::assertSame([201, 401, 422], array_keys((array) Schemas::at($document, 'paths', '/api/books', 'post', 'responses')));
        self::assertSame([204, 401, 404], array_keys((array) Schemas::at($document, 'paths', '/api/books/{book}', 'delete', 'responses')));
        self::assertStringNotContainsString('#[Response(204)]', self::code('recipes.md', 'CRUD resource'));
        self::assertSame('book', Schemas::at($document, 'paths', '/api/books/{book}', 'parameters', '0', 'name'));
        self::assertSame(['id', 'title', 'price', 'published_at'], array_keys((array) Schemas::at($document, 'components', 'schemas', 'Book', 'properties')));
    }

    public function test_the_declared_responses_recipe(): void
    {
        $responses = Schemas::at($this->recipe('Declared responses and exceptions'), 'paths', '/api/seats/{seat}/booking', 'post', 'responses');

        self::assertSame([201, 409, 403], array_keys((array) $responses));
        self::assertIsArray($responses);
        self::assertSame(['Location'], array_keys((array) Schemas::at($responses, '201', 'headers')));
        self::assertSame('The seat is already taken (SeatTakenException)', Schemas::at($responses, '409', 'description'));
    }

    public function test_the_described_requests_recipe(): void
    {
        $path = Schemas::at($this->recipe('Requests described by hand'), 'paths', '/api/catalogs/{catalog}/search');
        self::assertIsArray($path);
        $operation = Schemas::at($path, 'get');
        self::assertIsArray($operation);
        $parameters = [];

        foreach ((array) Schemas::at($operation, 'parameters') as $parameter) {
            self::assertIsArray($parameter);
            $name = $parameter['name'] ?? null;
            self::assertIsString($name);
            $parameters[$name] = $parameter;
        }

        self::assertSame(['q', 'isbn', 'page', 'X-Tenant', 'sort'], array_keys($parameters));
        self::assertSame('Catalog code', Schemas::at($path, 'parameters', '0', 'description'));
        self::assertSame('^[0-9-]{10,17}$', Schemas::at($parameters['isbn'], 'schema', 'pattern'));
        self::assertSame(['title', 'price'], Schemas::at($parameters['sort'], 'schema', 'enum'));
    }

    public function test_the_webhooks_recipe(): void
    {
        $document = $this->recipe('Webhooks and callbacks');

        self::assertSame(['/api/orders'], array_keys((array) Schemas::at($document, 'paths')));
        self::assertSame(['order.paid'], array_keys((array) Schemas::at($document, 'webhooks')));
        $this->post('/api/webhooks/orders/paid', ['id' => 1, 'total' => 10, 'status' => 'paid'])->assertNotFound();
        self::assertSame(['orderPaid'], array_keys((array) Schemas::at($document, 'paths', '/api/orders', 'post', 'callbacks')));
    }

    public function test_the_extensions_recipe(): void
    {
        $operation = Schemas::at($this->recipe('Extending the generator'), 'paths', '/api/contact', 'post');
        self::assertIsArray($operation);

        self::assertSame('2024-06', $operation['x-api-version'] ?? null);
        self::assertSame('^\+[1-9][0-9]{6,14}$', Schemas::at($operation, 'requestBody', 'content', 'application/json', 'schema', 'properties', 'phone', 'pattern'));
    }

    #[Group('json-api')]
    public function test_the_json_api_recipe(): void
    {
        $operation = Schemas::at($this->recipe('JSON:API resources'), 'paths', '/api/articles/{article}', 'get');
        self::assertIsArray($operation);

        self::assertSame(['application/vnd.api+json'], array_keys((array) Schemas::at($operation, 'responses', '200', 'content')));
        self::assertSame(['include', 'fields'], array_map(static fn(mixed $parameter): mixed => is_array($parameter) ? $parameter['name'] ?? null : null, (array) Schemas::at($operation, 'parameters')));
    }

    /**
     * @return array<array-key, mixed>
     */
    private function recipe(string $title): array
    {
        return $this->example('recipes.md', $title);
    }

    /**
     * The `### #[Name]` section of the reference.
     */
    private static function section(string $reference, string $attribute): string
    {
        $sections = preg_split('/^(?=### )/m', $reference);

        foreach ($sections === false ? [] : $sections as $section) {
            $heading = strtok($section, "\n");

            if (str_starts_with($section, '### ') && is_string($heading) && str_contains($heading, "`#[{$attribute}]`")) {
                return $section;
            }
        }

        self::fail("reference.md has no section for #[{$attribute}].");
    }
}
