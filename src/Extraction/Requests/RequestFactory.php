<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Requests;

use Ark4ne\OpenApi\Description\ParameterDescription;
use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApi\Extraction\Rules\SchemaTypes;
use Ark4ne\OpenApiWriter\Enums\HttpMethod;
use BackedEnum;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Throwable;

/**
 * Builds the request class of an action without running it: no validation, no `authorize()`.
 *
 * The request knows its route, whose parameters are bound to samples: a new model, the first case of an enum, `1`
 * for an integer, else `sample`. `rules()` is called by the container, which injects its arguments.
 */
final readonly class RequestFactory
{
    public function __construct(
        private Container $container,
    ) {}

    /**
     * @template T of Request
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    public function make(string $class, OperationContext $operation): Request
    {
        $samples = [];

        foreach ($operation->route->parameters as $parameter) {
            $samples[$parameter->name] = self::sample($parameter);
        }

        $uri = (string) preg_replace_callback('/\{(\w+)}/', static fn(array $match): string => is_scalar($samples[$match[1]] ?? null) ? (string) $samples[$match[1]] : '1', $operation->path);
        $method = $operation->method instanceof HttpMethod ? strtoupper($operation->method->value) : $operation->method;
        $request = $class::create($uri, $method);

        if (method_exists($request, 'setContainer')) {
            $request->setContainer($this->container);
        }

        $route = clone $operation->route->route;
        $route->parameters = $samples;
        $request->setRouteResolver(static fn() => $route);

        return $request;
    }

    /**
     * The rules of a request, its method arguments injected.
     *
     * @return array<array-key, mixed>
     */
    public function rules(Request $request): array
    {
        if (! method_exists($request, 'rules')) {
            return [];
        }

        $rules = $this->container->call([$request, 'rules']);

        return is_array($rules) ? $rules : [];
    }

    private static function sample(ParameterDescription $parameter): mixed
    {
        $class = $parameter->boundTo;

        try {
            return match (true) {
                $class !== null && is_subclass_of($class, BackedEnum::class) => $class::cases()[0] ?? null,
                $class !== null => new $class(),
                SchemaTypes::of($parameter->schema) === 'integer' => 1,
                default => 'sample',
            };
        } catch (Throwable) {
            return null;
        }
    }
}
