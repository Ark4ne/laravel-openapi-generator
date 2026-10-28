<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Extraction\Strategies;

use Ark4ne\OpenApi\Configuration\StrategyConfiguration;
use Ark4ne\OpenApi\Discovery\RouteDefinition;
use Ark4ne\OpenApi\Exceptions\InvalidConfigurationException;
use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApi\Extraction\Strategies\BuiltInGroupStrategy;
use Ark4ne\OpenApi\Extraction\Strategies\BuiltInOperationIdStrategy;
use Ark4ne\OpenApi\Extraction\Strategies\BuiltInStrategies;
use Ark4ne\OpenApi\Extraction\Strategies\BuiltInSummaryStrategy;
use Ark4ne\OpenApi\Extraction\Strategies\BuiltInTagStrategy;
use Ark4ne\OpenApi\Extraction\Strategies\RouteValues;
use Ark4ne\OpenApi\Extraction\Strategies\TagStrategy;
use Ark4ne\OpenApi\Tests\Fixtures\Metadata\Controllers\Admin\UserController as AdminUserController;
use Ark4ne\OpenApi\Tests\Fixtures\Metadata\Controllers\UserController;
use Ark4ne\OpenApi\Tests\Fixtures\Metadata\FixedSummaryStrategy;
use Ark4ne\OpenApi\Tests\Fixtures\Types\Sibling;
use Ark4ne\OpenApi\Types\TypeResolver;
use Ark4ne\OpenApiWriter\Enums\HttpMethod;
use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionFunction;

#[CoversClass(BuiltInSummaryStrategy::class)]
#[CoversClass(BuiltInTagStrategy::class)]
#[CoversClass(BuiltInGroupStrategy::class)]
#[CoversClass(BuiltInOperationIdStrategy::class)]
#[CoversClass(BuiltInStrategies::class)]
#[CoversClass(RouteValues::class)]
final class BuiltInStrategiesTest extends TestCase
{
    private const CONTROLLER = AdminUserController::class;

    /**
     * @return iterable<string, array{string, string|null, string|null}>
     */
    public static function summaries(): iterable
    {
        yield 'doc' => ['doc', null, 'List the users.'];
        yield 'name' => ['name', null, 'api.users.index'];
        yield 'uri' => ['uri', null, '/api/v1/users/{user}'];
        yield 'controller' => ['controller', null, 'UserController@index'];
        yield 'pattern group' => ['name', '/^api\.(.+)$/', 'users.index'];
        yield 'pattern without match' => ['name', '/^web\.(.+)$/', 'api.users.index'];
    }

    #[DataProvider('summaries')]
    public function test_the_summary_strategy_reads_its_source(string $by, ?string $pattern, ?string $expected): void
    {
        self::assertSame($expected, (new BuiltInSummaryStrategy($by, $pattern))->summary(self::operation()));
    }

    public function test_a_controller_without_the_controller_suffix_keeps_its_name(): void
    {
        $operation = self::operation(controller: Sibling::class);

        self::assertSame('Sibling@index', (new BuiltInSummaryStrategy('controller'))->summary($operation));
        self::assertSame('Sibling.index', (new BuiltInOperationIdStrategy('action'))->operationId($operation));
        self::assertSame(['Sibling'], (new BuiltInTagStrategy('controller'))->tags($operation));
    }

    public function test_a_summary_source_the_route_lacks_gives_none(): void
    {
        self::assertNull((new BuiltInSummaryStrategy('controller'))->summary(self::operation(controller: null)));
        self::assertNull((new BuiltInSummaryStrategy('name'))->summary(self::operation(name: null)));
        self::assertNull((new BuiltInSummaryStrategy('doc'))->summary(self::operation(doc: false)));
    }

    /**
     * @return iterable<string, array{string, string|null, list<string>}>
     */
    public static function tags(): iterable
    {
        yield 'controller basename' => ['controller', null, ['User']];
        yield 'controller pattern' => ['controller', '/^Ark4ne\\\\OpenApi\\\\Tests\\\\Fixtures\\\\Metadata\\\\Controllers\\\\(.+)Controller$/', ['Admin\User']];
        yield 'controller pattern without match' => ['controller', '/^Domain\\\\(.+)$/', ['User']];
        yield 'uri resource' => ['uri', null, ['users']];
        yield 'uri pattern' => ['uri', '/^api\/(v\d+)/', ['v1']];
        yield 'name resource' => ['name', null, ['users']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('tags')]
    public function test_the_tag_strategy_reads_its_source(string $by, ?string $pattern, array $expected): void
    {
        self::assertSame($expected, (new BuiltInTagStrategy($by, $pattern))->tags(self::operation()));
    }

    public function test_a_tag_source_the_route_lacks_gives_no_tag(): void
    {
        self::assertSame([], (new BuiltInTagStrategy('controller'))->tags(self::operation(controller: null)));
        self::assertSame([], (new BuiltInTagStrategy('uri'))->tags(self::operation(path: '/{user}')));
    }

    /**
     * @return iterable<string, array{string, string|null, string, string|null}>
     */
    public static function groupCases(): iterable
    {
        yield 'controller namespace' => ['controller', null, 'User', 'Admin'];
        yield 'uri first segment' => ['uri', null, 'users', 'api'];
        yield 'name first segment' => ['name', null, 'users', 'api'];
        yield 'pattern' => ['name', '/^api\.([^.]+)/', 'Users', 'users'];
        yield 'pattern without match' => ['name', '/^web\./', 'users', null];
        yield 'same as the tag' => ['name', null, 'api', null];
    }

    #[DataProvider('groupCases')]
    public function test_the_group_strategy_reads_its_source(string $by, ?string $pattern, string $tag, ?string $expected): void
    {
        self::assertSame($expected, (new BuiltInGroupStrategy($by, $pattern))->group($tag, self::operation()));
    }

    public function test_a_controller_outside_a_namespace_of_controllers_has_no_group(): void
    {
        self::assertNull((new BuiltInGroupStrategy('controller'))->group('User', self::operation(controller: UserController::class)));
        self::assertNull((new BuiltInGroupStrategy('controller'))->group('User', self::operation(controller: null)));
    }

    /**
     * @return iterable<string, array{string, OperationContext, string}>
     */
    public static function operationIds(): iterable
    {
        yield 'name' => ['name', self::operation(), 'api.users.index'];
        yield 'action' => ['action', self::operation(), 'UserController.index'];
        yield 'method and path' => ['method_path', self::operation(), 'getApiV1UsersByUser'];
        yield 'name without name' => ['name', self::operation(name: null), 'getApiV1UsersByUser'];
        yield 'action of a closure' => ['action', self::operation(controller: null), 'getApiV1UsersByUser'];
        yield 'another method' => ['method_path', self::operation(method: 'PURGE', path: '/cache'), 'purgeCache'];
    }

    #[DataProvider('operationIds')]
    public function test_the_operation_id_strategy_reads_its_source(string $by, OperationContext $operation, string $expected): void
    {
        self::assertSame($expected, (new BuiltInOperationIdStrategy($by))->operationId($operation));
    }

    public function test_the_factory_builds_the_built_in_strategies(): void
    {
        self::assertInstanceOf(BuiltInSummaryStrategy::class, BuiltInStrategies::summary(new StrategyConfiguration('uri')));
        self::assertInstanceOf(BuiltInTagStrategy::class, BuiltInStrategies::tags(new StrategyConfiguration('name', ['pattern' => '/(.+)/'])));
        self::assertInstanceOf(BuiltInGroupStrategy::class, BuiltInStrategies::groups(new StrategyConfiguration('controller')));
        self::assertNull(BuiltInStrategies::groups(null));
        self::assertInstanceOf(BuiltInOperationIdStrategy::class, BuiltInStrategies::operationId(new StrategyConfiguration('action')));
    }

    public function test_the_factory_builds_a_strategy_class(): void
    {
        $strategy = BuiltInStrategies::summary(new StrategyConfiguration(FixedSummaryStrategy::class));

        self::assertSame('Fixed', $strategy->summary(self::operation()));
    }

    public function test_the_factory_rejects_an_unknown_strategy(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('openapi.documents.v1.strategies.tags.by: expected one of controller, uri, name or a class implementing ' . TagStrategy::class . ', got "folder"');

        BuiltInStrategies::tags(new StrategyConfiguration('folder'), 'documents.v1.strategies.tags');
    }

    public function test_the_factory_rejects_a_class_of_another_interface(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        BuiltInStrategies::operationId(new StrategyConfiguration(FixedSummaryStrategy::class));
    }

    /**
     * `GET /api/v1/users/{user}`, route `api.users.index`, `…\Controllers\Admin\UserController@index`.
     *
     * @param class-string|null $controller
     */
    private static function operation(
        ?string $name = 'api.users.index',
        ?string $controller = self::CONTROLLER,
        HttpMethod|string $method = HttpMethod::Get,
        string $path = '/api/v1/users/{user}',
        bool $doc = true,
    ): OperationContext {
        $route = new Route(['GET'], ltrim($path, '/'), static fn() => null);
        $definition = new RouteDefinition($route, $method, $path, $name, controller: $controller, action: $controller === null ? null : 'index');
        $docBlock = $doc ? (new TypeResolver())->docBlock(new ReflectionFunction(/** List the users. */ static fn() => null)) : null;

        return new OperationContext($definition, $method, $path, doc: $docBlock);
    }
}
