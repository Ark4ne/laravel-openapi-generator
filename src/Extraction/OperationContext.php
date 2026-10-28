<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction;

use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Discovery\RouteDefinition;
use Ark4ne\OpenApi\Types\DocBlock;
use Ark4ne\OpenApi\Types\NullableType;
use Ark4ne\OpenApi\Types\Type;
use Ark4ne\OpenApi\Types\UnionType;
use Ark4ne\OpenApiWriter\Enums\HttpMethod;
use ReflectionClass;
use ReflectionFunctionAbstract;
use ReflectionMethod;

/**
 * What the extractors know about the operation they describe: its route, its method and its resolved action.
 *
 * Built by the OperationContextFactory.
 */
final readonly class OperationContext
{
    /**
     * @param ReflectionFunctionAbstract|null $action controller method or closure; `null` when it cannot be resolved
     * @param DocBlock|null $doc PHPDoc of the action; empty when it has none
     * @param Type|null $requestType type of the request parameter of the action (a `Request` subclass)
     * @param Type|null $returnType declared return type of the action (PHPDoc, else native)
     * @param list<Type> $throws exceptions of the `@throws` of the action
     */
    public function __construct(
        public RouteDefinition $route,
        public HttpMethod|string $method,
        public string $path,
        public ?ReflectionFunctionAbstract $action = null,
        public ?DocBlock $doc = null,
        public ?Type $requestType = null,
        public ?Type $returnType = null,
        public array $throws = [],
    ) {}

    /**
     * The description extraction starts from: method, path, route label and middlewares.
     */
    public function describe(): OperationDescription
    {
        return OperationDescription::create($this->method, $this->path)
            ->route($this->route->label())
            ->middlewares(...$this->route->middlewares);
    }

    /**
     * The controller of a controller action.
     *
     * @return ReflectionClass<object>|null
     */
    public function controller(): ?ReflectionClass
    {
        return $this->action instanceof ReflectionMethod ? $this->action->getDeclaringClass() : null;
    }

    /**
     * Members of the return type: one per member of a union, `null` left out.
     *
     * @return list<Type>
     */
    public function returnTypes(): array
    {
        $type = $this->returnType instanceof NullableType ? $this->returnType->type : $this->returnType;

        return match (true) {
            $type === null => [],
            $type instanceof UnionType => $type->types,
            default => [$type],
        };
    }

    /**
     * Instances of an attribute: those of the controller class first, then those of the action.
     *
     * @template T of object
     *
     * @param class-string<T> $attribute
     *
     * @return list<T>
     */
    public function attributes(string $attribute): array
    {
        return [...$this->classAttributes($attribute), ...$this->actionAttributes($attribute)];
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $attribute
     *
     * @return list<T>
     */
    public function classAttributes(string $attribute): array
    {
        return array_map(static fn($instance): object => $instance->newInstance(), $this->controller()?->getAttributes($attribute) ?? []);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $attribute
     *
     * @return list<T>
     */
    public function actionAttributes(string $attribute): array
    {
        return array_map(static fn($instance): object => $instance->newInstance(), $this->action?->getAttributes($attribute) ?? []);
    }
}
