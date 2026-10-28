<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Feature\Requests;

use Ark4ne\OpenApi\Description\ParameterDescription;
use Ark4ne\OpenApi\Description\RequestDescription;
use Ark4ne\OpenApi\Extraction\OperationContextFactory;
use Ark4ne\OpenApi\Extraction\Requests\RequestExtractors;
use Ark4ne\OpenApi\Extraction\Strategies\BuiltInQueryStrategy;
use Ark4ne\OpenApi\Tests\Fixtures\Requests\Legacy\LegacyController;
use Ark4ne\OpenApi\Tests\Support\Schemas;
use Ark4ne\OpenApi\Tests\TestCase;
use Ark4ne\OpenApi\Types\TypeResolver;
use Ark4ne\OpenApiWriter\Objects\Schema;
use Illuminate\Contracts\Container\Container;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Requests of the v0.6 test application (`openapi-jsonresource.json`), with the default `all` query mode. The
 * expected documents come with the writing (E8).
 */
#[CoversNothing]
final class RequestsTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        $router->get('user', [LegacyController::class, 'users']);
        $router->post('user', [LegacyController::class, 'store']);
        $router->put('user/{id}', [LegacyController::class, 'update']);
        $router->get('post', [LegacyController::class, 'posts']);
        $router->get('comment', [LegacyController::class, 'comments']);
    }

    public function test_the_query_parameters_are_those_of_v0_6(): void
    {
        $requests = $this->requests();

        self::assertSame(['filter[][name]' => ['type' => 'string'], 'filter[][value][]' => ['type' => 'string']], self::queryParameters($requests['GET /user']));
        // v0.6 described `max:today` as a timestamp in the description; it is not a size of a date.
        self::assertSame(['filter[user][]' => ['type' => 'integer'], 'filter[has_comment]' => ['type' => 'boolean'], 'filter[date]' => ['description' => 'A date.', 'type' => 'string']], self::queryParameters($requests['GET /post']));
        self::assertSame(['filter[user][]' => ['type' => 'integer'], 'filter[post][]' => ['type' => 'integer']], self::queryParameters($requests['GET /comment']));
    }

    public function test_the_bodies_are_those_of_v0_6_with_their_required_fields(): void
    {
        $requests = $this->requests();

        // v0.6 lost the required fields.
        Schemas::assertSame('{"type":"object","properties":{"name":{"type":"string"},"email":{"type":"string"}},"required":["name","email"]}', $requests['POST /user']->body->contents['application/json'] ?? Schema::create());
        Schemas::assertSame('{"type":"object","properties":{"name":{"type":"string"}}}', $requests['PUT /user/{id}']->body->contents['application/json'] ?? Schema::create());
        self::assertSame(['id'], array_map(static fn(ParameterDescription $parameter): string => $parameter->name, $requests['PUT /user/{id}']->parameters));
    }

    /**
     * @return array<string, RequestDescription>
     */
    private function requests(): array
    {
        $context = $this->context();
        $container = $this->app?->make(Container::class);
        self::assertInstanceOf(Container::class, $container);
        $factory = new OperationContextFactory(new TypeResolver());
        $extractors = RequestExtractors::default($container, new BuiltInQueryStrategy('all'));
        $requests = [];

        foreach ($this->definitions($context) as $definition) {
            $requests[$definition->label()] = $extractors->extract($factory->create($definition, $context), RequestDescription::create(), $context);
        }

        self::assertDiagnostics([], $context);

        return $requests;
    }

    /**
     * @return array<string, mixed>
     */
    private static function queryParameters(RequestDescription $request): array
    {
        $parameters = [];

        foreach ($request->parameters as $parameter) {
            $parameters[$parameter->name] = json_decode($parameter->schema->toJson(), true);
        }

        return $parameters;
    }
}
