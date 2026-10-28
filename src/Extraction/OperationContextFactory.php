<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApi\Discovery\RouteDefinition;
use Ark4ne\OpenApi\Types\Type;
use Ark4ne\OpenApi\Types\TypeResolver;
use Closure;
use Illuminate\Http\Request;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/**
 * Resolves the action of a route and what the extractors read from it: PHPDoc, request type, return type and
 * declared exceptions.
 */
final readonly class OperationContextFactory
{
    public function __construct(
        private TypeResolver $types,
    ) {}

    public function create(RouteDefinition $route, GenerationContext $context): OperationContext
    {
        $action = $this->action($route, $context);

        if ($action === null) {
            return new OperationContext($route, $route->method, $route->path);
        }

        return new OperationContext(
            $route,
            $route->method,
            $route->path,
            $action,
            $this->types->docBlock($this->types->documented($action)),
            $this->requestType($action),
            $this->types->returnType($action),
            $context->configuration->exceptions->readThrows ? $this->types->throws($action) : [],
        );
    }

    private function action(RouteDefinition $route, GenerationContext $context): ?ReflectionFunctionAbstract
    {
        if ($route->isClosure()) {
            $uses = $route->route->getAction('uses');

            return $uses instanceof Closure ? new ReflectionFunction($uses) : null;
        }

        try {
            return new ReflectionMethod((string) $route->controller, (string) $route->action);
        } catch (Throwable) {
            $context->diagnostics->warning(Stage::Operation, "cannot resolve the action {$route->controller}@{$route->action}", $route->label());

            return null;
        }
    }

    /**
     * Type of the first parameter that receives the request (`Request` or a subclass, such as a FormRequest).
     */
    private function requestType(ReflectionFunctionAbstract $action): ?Type
    {
        foreach ($action->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && ! $type->isBuiltin() && is_a($type->getName(), Request::class, true)) {
                return $this->types->parameterType($parameter);
            }
        }

        return null;
    }
}
