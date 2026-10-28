<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Extraction;

use Ark4ne\OpenApi\Attributes\Operation;
use Ark4ne\OpenApi\Attributes\Tag;
use Ark4ne\OpenApi\Configuration\ExceptionsConfiguration;
use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApi\Extraction\OperationContextFactory;
use Ark4ne\OpenApi\Tests\Fixtures\Metadata\Controllers\UserController;
use Ark4ne\OpenApi\Tests\Fixtures\Metadata\Requests\StoreUserRequest;
use Ark4ne\OpenApi\Tests\Fixtures\Types\PostResource;
use Ark4ne\OpenApi\Tests\Fixtures\Types\UserResource;
use Ark4ne\OpenApi\Tests\TestCase;
use Ark4ne\OpenApi\Types\Type;
use Ark4ne\OpenApiWriter\Enums\HttpMethod;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionFunction;
use ReflectionMethod;

#[CoversClass(OperationContextFactory::class)]
#[CoversClass(OperationContext::class)]
final class OperationContextFactoryTest extends TestCase
{
    public function test_a_controller_action_is_resolved_with_its_phpdoc_and_types(): void
    {
        $this->router()->get('api/users/{user}', [UserController::class, 'show']);

        $operation = $this->operation();

        self::assertInstanceOf(ReflectionMethod::class, $operation->action);
        self::assertSame('show', $operation->action->getName());
        self::assertSame(UserController::class, $operation->controller()?->getName());
        self::assertSame('Show a user.', $operation->doc?->summary());
        self::assertSame(HttpMethod::Get, $operation->method);
        self::assertSame('/api/users/{user}', $operation->path);
        self::assertSame(UserResource::class . '|' . PostResource::class . '|null', $operation->returnType?->toString());
        self::assertSame([UserResource::class, PostResource::class], array_map(static fn(Type $type): string => $type->toString(), $operation->returnTypes()));
        self::assertSame([ModelNotFoundException::class, 'RuntimeException'], array_map(static fn(Type $type): string => $type->toString(), $operation->throws));
        self::assertNull($operation->requestType);
    }

    public function test_the_request_type_is_the_first_request_parameter(): void
    {
        $this->router()->post('api/users', [UserController::class, 'store']);

        self::assertSame(StoreUserRequest::class, $this->operation()->requestType?->toString());
    }

    public function test_the_throws_are_not_read_when_disabled(): void
    {
        $this->router()->get('api/users/{user}', [UserController::class, 'show']);

        self::assertSame([], $this->operation($this->context(exceptions: new ExceptionsConfiguration(readThrows: false)))->throws);
    }

    public function test_a_closure_is_resolved(): void
    {
        $this->router()->get('api/ping', /** Ping the API. */ static fn(): string => 'pong');

        $operation = $this->operation();

        self::assertInstanceOf(ReflectionFunction::class, $operation->action);
        self::assertNull($operation->controller());
        self::assertSame('Ping the API.', $operation->doc?->summary());
        self::assertSame('string', $operation->returnType?->toString());
        self::assertSame([], $operation->attributes(Operation::class, $this->context()));
    }

    public function test_a_missing_action_gives_an_empty_context_and_a_warning(): void
    {
        $this->router()->get('api/missing', [UserController::class, 'missing']);
        $context = $this->context();

        $operation = $this->operation($context);

        self::assertNull($operation->action);
        self::assertNull($operation->doc);
        self::assertSame([], $operation->returnTypes());
        self::assertDiagnostics(['[warning] operation GET /api/missing: cannot resolve the action ' . UserController::class . '@missing'], $context);
    }

    public function test_the_attributes_of_the_class_come_before_those_of_the_action(): void
    {
        $this->router()->post('api/users', [UserController::class, 'store']);

        $tags = array_map(static fn(Tag $tag): string => $tag->name, $this->operation()->attributes(Tag::class, $this->context()));

        self::assertSame(['users', 'admin'], $tags);
    }

    public function test_the_description_starts_from_the_route(): void
    {
        $this->router()->get('api/users', [UserController::class, 'index'])->middleware('auth:sanctum');

        $description = $this->operation()->describe();

        self::assertSame(HttpMethod::Get, $description->method);
        self::assertSame('/api/users', $description->path);
        self::assertSame('GET /api/users', $description->route);
        self::assertSame(['auth:sanctum'], $description->middlewares);
    }
}
