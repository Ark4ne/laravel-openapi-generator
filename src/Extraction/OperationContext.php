<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Discovery\RouteDefinition;
use Ark4ne\OpenApi\Types\DocBlock;
use Ark4ne\OpenApi\Types\NullableType;
use Ark4ne\OpenApi\Types\Type;
use Ark4ne\OpenApi\Types\UnionType;
use Ark4ne\OpenApiWriter\Enums\HttpMethod;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use Throwable;

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
     * The controller of the route, which may inherit its action from a parent.
     *
     * @return ReflectionClass<object>|null
     */
    public function controller(): ?ReflectionClass
    {
        $controller = $this->route->controller;

        if ($controller !== null && class_exists($controller)) {
            return new ReflectionClass($controller);
        }

        return $this->action instanceof ReflectionMethod ? $this->action->getDeclaringClass() : null;
    }

    /**
     * The controller and its parents, from the highest parent down to the controller.
     *
     * @return list<ReflectionClass<object>>
     */
    public function controllers(): array
    {
        $classes = [];

        $class = $this->controller();

        while ($class instanceof ReflectionClass) {
            array_unshift($classes, $class);
            $parent = $class->getParentClass();
            $class = $parent === false ? null : $parent;
        }

        return $classes;
    }

    /**
     * Each declaration of the action along the controllers, from the highest parent down to the redefinition
     * the route calls; the closure alone for a closure.
     *
     * @return list<ReflectionFunctionAbstract>
     */
    public function actions(): array
    {
        if (! $this->action instanceof ReflectionMethod) {
            return $this->action === null ? [] : [$this->action];
        }

        $name = $this->action->getName();
        $actions = [];

        foreach ($this->controllers() as $class) {
            if ($class->hasMethod($name) && $class->getMethod($name)->getDeclaringClass()->getName() === $class->getName()) {
                $actions[] = $class->getMethod($name);
            }
        }

        return $actions === [] ? [$this->action] : $actions;
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
     * Instances of an attribute: those of the controllers first, then those of the declarations of the action, each
     * from the highest parent down (the last instance is the child's).
     *
     * An attribute that cannot be built (unknown or wrong argument) is left out with a warning.
     *
     * @template T of object
     *
     * @param class-string<T> $attribute
     *
     * @return list<T>
     */
    public function attributes(string $attribute, GenerationContext $context): array
    {
        return [...$this->classAttributes($attribute, $context), ...$this->actionAttributes($attribute, $context)];
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $attribute
     *
     * @return list<T>
     */
    public function classAttributes(string $attribute, GenerationContext $context): array
    {
        $instances = [];

        foreach ($this->controllers() as $class) {
            array_push($instances, ...$this->instances($class->getAttributes($attribute), $class->getName(), $context));
        }

        return $instances;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $attribute
     *
     * @return list<T>
     */
    public function actionAttributes(string $attribute, GenerationContext $context): array
    {
        $instances = [];

        foreach ($this->actions() as $action) {
            $target = $action instanceof ReflectionMethod ? "{$action->getDeclaringClass()->getName()}::{$action->getName()}" : $this->route->label();
            array_push($instances, ...$this->instances($action->getAttributes($attribute), $target, $context));
        }

        return $instances;
    }

    /**
     * @template T of object
     *
     * @param list<ReflectionAttribute<T>> $attributes
     *
     * @return list<T>
     */
    private function instances(array $attributes, string $target, GenerationContext $context): array
    {
        $instances = [];

        foreach ($attributes as $attribute) {
            try {
                $instances[] = $attribute->newInstance();
            } catch (Throwable $error) {
                $context->diagnostics->warning(
                    Stage::Operation,
                    "invalid #[{$attribute->getName()}] on {$target}, attribute ignored: {$error->getMessage()}",
                    $this->route->label(),
                );
            }
        }

        return $instances;
    }
}
