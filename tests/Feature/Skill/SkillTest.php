<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Feature\Skill;

use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Laravel\GeneratorFactory;
use Ark4ne\OpenApi\Output\DocumentValidator;
use Ark4ne\OpenApi\Tests\Feature\Laravel\LaravelTestCase;
use Ark4ne\OpenApi\Tests\Support\Schemas;
use Ark4ne\OpenApiWriter\Enums\Version;
use Ark4ne\OpenApiWriter\Validation\OpisJsonSchemaValidator;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use ReflectionClass;

/**
 * The agent skill shipped with the package (`skills/laravel-openapi-generator/`): every recipe runs in an
 * application and gives a valid document in 3.0, 3.1 and 3.2, the files link to each other, and the reference names
 * every attribute, every configuration key and every extension point.
 */
#[CoversNothing]
final class SkillTest extends LaravelTestCase
{
    private const DIRECTORY = __DIR__ . '/../../../skills/laravel-openapi-generator/';

    public function test_the_skill_declares_its_name_and_when_to_use_it(): void
    {
        self::assertSame(1, preg_match('/\A---\nname: laravel-openapi-generator\ndescription: (.+)\n---\n/', self::read('SKILL.md'), $matches));
        self::assertLessThanOrEqual(1024, strlen($matches[1]));
        self::assertStringContainsString('Ark4ne\OpenApi', $matches[1]);
    }

    public function test_every_link_between_the_files_of_the_skill_resolves(): void
    {
        foreach (['SKILL.md', 'reference.md', 'recipes.md'] as $file) {
            preg_match_all('/\]\(([^)\s]+)\)/', self::read($file), $links);

            foreach ($links[1] as $link) {
                if (preg_match('#^[a-z]+://#', $link) === 1) {
                    continue;
                }

                [$path, $anchor] = array_pad(explode('#', $link, 2), 2, null);
                $target = $path === '' ? self::DIRECTORY . $file : self::DIRECTORY . $path;

                self::assertFileExists($target, "{$file}: {$link}");

                if ($anchor !== null) {
                    self::assertContains($anchor, self::anchors((string) file_get_contents($target)), "{$file}: {$link}");
                }
            }
        }
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
        self::assertStringNotContainsString('#[Response(204)]', self::code('CRUD resource'));
        self::assertSame('book', Schemas::at($document, 'paths', '/api/books/{book}', 'parameters', '0', 'name'));
        self::assertSame(['id', 'title', 'price', 'published_at'], array_keys((array) Schemas::at($document, 'components', 'schemas', 'Book', 'properties')));
    }

    public function test_the_declared_responses_recipe(): void
    {
        $responses = Schemas::at($this->recipe('Declared responses and exceptions'), 'paths', '/api/seats/{seat}/hold', 'post', 'responses');

        self::assertSame([200, 409, 403], array_keys((array) $responses));
        self::assertIsArray($responses);
        self::assertSame(['Retry-After'], array_keys((array) Schemas::at($responses, '200', 'headers')));
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
     * The anchors GitHub gives to the headings of a Markdown file.
     *
     * @return list<string>
     */
    private static function anchors(string $markdown): array
    {
        preg_match_all('/^#{1,6} (.+)$/m', $markdown, $headings);

        return array_map(static fn(string $heading): string => str_replace(' ', '-', (string) preg_replace('/[^a-z0-9 _-]/', '', strtolower($heading))), $headings[1]);
    }

    /**
     * Runs the block of a recipe as `routes/api.php` (its routes under the `api` prefix), then generates and validates
     * its document in each line with the published configuration; returns the 3.1 one.
     *
     * @return array<array-key, mixed>
     */
    private function recipe(string $title): array
    {
        $code = self::code($title);
        Route::prefix('api')->group(static function () use ($code): void {
            eval($code);
        });
        $config = $this->app?->make(Repository::class);
        self::assertInstanceOf(Repository::class, $config);
        // Written in 3.0 too: what 3.0 cannot write (webhooks) is dropped instead of failing.
        $config->set('openapi.documents.v1.strict', false);
        $config->set('openapi.sampling.transactions', false);
        $written = [];

        foreach (['3.0' => Version::V3_0_4, '3.1' => Version::V3_1_2, '3.2' => Version::V3_2_0] as $line => $version) {
            $config->set('openapi.documents.v1.openapi', $version->value);
            $settings = $config->get('openapi');
            self::assertIsArray($settings);
            $configuration = Configuration::fromArray($settings);
            $factory = $this->app?->make(GeneratorFactory::class);
            self::assertInstanceOf(GeneratorFactory::class, $factory);
            $generated = $factory->make($configuration)->generate($configuration, 'v1');
            $violations = (new DocumentValidator(new OpisJsonSchemaValidator()))->validate($generated->openApi, $generated->context);

            self::assertSame([], array_map(strval(...), $violations->errors()), "{$title} ({$line})");
            $written[$line] = $generated->openApi;
        }

        return $written['3.1']->toArray(Version::V3_1_2);
    }

    /**
     * The `php` block of a recipe, without its `skip` blocks.
     */
    private static function code(string $title): string
    {
        $sections = preg_split('/^## /m', self::read('recipes.md'));

        foreach ($sections === false ? [] : $sections as $section) {
            if (str_starts_with($section, "{$title}\n") && preg_match('/^```php\n(.*?)^```$/ms', $section, $block) === 1) {
                return $block[1];
            }
        }

        self::fail("recipes.md has no runnable block for {$title}.");
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

    private static function read(string $file): string
    {
        return (string) file_get_contents(self::DIRECTORY . $file);
    }
}
