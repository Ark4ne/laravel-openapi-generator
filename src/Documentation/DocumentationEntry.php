<?php

namespace Ark4ne\OpenApi\Documentation;

use Ark4ne\OpenApi\Contracts\Entry;
use Ark4ne\OpenApi\Support\ArrayInsensitive;
use Ark4ne\OpenApi\Support\Config;
use Ark4ne\OpenApi\Support\Reflection;
use Ark4ne\OpenApi\Support\Support;
use Ark4ne\OpenApi\Support\Trans;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use phpDocumentor\Reflection\DocBlock;
use ReflectionMethod;

class DocumentationEntry implements Entry
{
    protected string $name;
    protected string $uri;
    /** @var array<string> */
    protected array $parameters;
    protected mixed $controller;
    protected string $action;
    protected ReflectionMethod $method;
    protected null|DocBlock $doc;
    protected null|Reflection\Type $requestClass;
    /** @var Reflection\Type[]|Reflection\Type|null */
    protected null|array|Reflection\Type $responseClass;
    protected RequestEntry $request;
    /** @var \Ark4ne\OpenApi\Documentation\ResponseEntry[] */
    protected array $responses;

    public function __construct(
        protected Router $router,
        protected Route  $route
    )
    {
    }

    /**
     * @return array<string>
     */
    public function getMethods(): array
    {
        return $this->route->methods();
    }

    public function getRouteUri(): string
    {
        return $this->uri ??= $this->route->uri();
    }

    public function getRouteName(): string
    {
        return $this->name ??= $this->route->getName() ?? Str::slug(
            $this->getControllerClass() . '-' . $this->getAction(),
            '.'
        );
    }

    /**
     * @return array<string, null|string>
     * @throws \ReflectionException
     */
    public function getRouteParams(): array
    {
        if (isset($this->parameters)) {
            return $this->parameters;
        }

        /** @var \Symfony\Component\Routing\CompiledRoute $compileRoute */
        $compileRoute = Reflection::call($this->route, 'compileRoute');

        $parameters = $compileRoute->getPathVariables();

        $parameters = array_merge(
            array_fill_keys($parameters, null),
            $this->route->action['wheres'] ?? [],
            $this->route->wheres
        );

        return $this->parameters = $parameters;
    }

    public function getController(): mixed
    {
        return $this->controller ??= $this->route->getController();
    }

    public function getControllerClass(): string
    {
        if (Support::method($this->route, 'getControllerClass')) {
            return $this->route->getControllerClass();
        }

        return $this->getController()::class;
    }

    public function getControllerName(): string
    {
        return substr(strrchr($this->getControllerClass(), "\\"), 1);
    }

    public function getAction(): string
    {
        if (isset($this->action)) {
            return $this->action;
        }

        $controller = $this->getControllerClass();
        $action = $this->route->getActionMethod();

        if ($action === $controller) {
            $action = '__invoke';
        }

        return $this->action = $action;
    }

    public function getMethod(): ReflectionMethod
    {
        return $this->method ??= Reflection::method($this->getControllerClass(), $this->getAction());
    }

    public function getDoc(): ?DocBlock
    {
        return $this->doc ??= Reflection::docblock($this->getMethod());
    }

    /**
     * @param string $tag
     *
     * @return \phpDocumentor\Reflection\DocBlock\Tags\BaseTag[]
     */
    public function getDocTag(string $tag): array
    {
        return $this->getDoc()?->getTagsByName("oa-$tag") ?? [];
    }

    public function getDocDescription(): ?string
    {
        return ($this->getDocTag('description')[0] ?? null)?->getDescription()?->getBodyTemplate();
    }

    public function getDocResponseStatus(): ?string
    {
        return trim(($this->getDocTag('response-status')[0] ?? null)?->getDescription()?->getBodyTemplate());
    }

    public function getDocResponseStatusCode(): ?int
    {
        $status = $this->getDocResponseStatus();

        if ($status) {
            [$code] = explode(' ', $status, 2);

            return (int)$code;
        }

        return null;
    }

    public function getDocResponseStatusName(): ?string
    {
        $status = $this->getDocResponseStatus();

        if ($status) {
            $parts = explode(' ', $status, 2);

            return $parts[2] ?? null;
        }

        return null;
    }

    public function getDocResponsePaginate(): bool
    {
        return (bool)($this->getDocTag('response-paginate')[0] ?? false);
    }

    /**
     * @return ArrayInsensitive<string, string>
     */
    public function getDocResponseHeaders(): ArrayInsensitive
    {
        $entries = array_map(
            static function ($tag) {
                $parts = explode(' ', $tag->getDescription()?->getBodyTemplate(), 2);

                return [$parts[0], $parts[1] ?? ''];
            },
            $this->getDocTag('response-header')
        );

        return new ArrayInsensitive(array_combine(
            array_column($entries, 0),
            array_column($entries, 1)
        ));
    }

    public function getName(): string
    {
        return Trans::get("openapi.requests.{$this->getRouteName()}.name")
            ?? $this->resolveGroup(Config::nameBy())
            ?? Str::studly($this->getRouteName());
    }

    public function getTag(): string
    {
        return $this->resolveGroup(Config::tagBy()) ?? $this->getControllerName();
    }

    public function getGroup(): ?string
    {
        return $this->resolveGroup(Config::groupBy());
    }

    public function getDescription(): ?string
    {
        if ($desc = $this->getDocDescription()) {
            return Trans::get("openapi.requests.descriptions.$desc", [], $desc);
        }

        return Trans::get("openapi.requests.{$this->getRouteName()}.description", [],
            $this->getDoc()?->getSummary() ?? '');
    }

    /**
     * @return \Ark4ne\OpenApi\Support\Reflection\Type<Response, mixed>[]|\Ark4ne\OpenApi\Support\Reflection\Type<Response, mixed>
     */
    public function getResponseClass(): Reflection\Type|array
    {
        if (isset($this->responseClass)) {
            return $this->responseClass;
        }

        $method = $this->getMethod();

        return $this->responseClass = Reflection::parseReturnType($method) ?? Reflection\Type::make(Response::class);
    }

    /**
     * @return \Ark4ne\OpenApi\Support\Reflection\Type<Request, null>
     */
    public function getRequestClass(): Reflection\Type
    {
        if (isset($this->requestClass)) {
            return $this->requestClass;
        }

        foreach ($this->route->signatureParameters() as $parameter) {
            if (!$parameter->hasType() || !($type = $parameter->getType())) {
                continue;
            }

            if ($type = Reflection::parseTypeHint($type, Request::class)) {
                return $this->requestClass = $type;
            }
        }

        $method = $this->getMethod();

        return $this->requestClass = Reflection::parseParametersFromDocBlockForClass($method, Request::class)
            ?? Reflection\Type::make(Request::class);
    }

    /**
     * @return string[]
     */
    public function getMiddlewares(): array
    {
        return $this->middlewares ??= array_unique(array_diff(
            array_merge($this->route->gatherMiddleware(), $this->router->gatherRouteMiddleware($this->route)),
            $this->route->excludedMiddleware()
        ));
    }

    private function compute(): void
    {
        $compute = new ComputeEntry($this);

        [$request, $responses] = $compute();

        $this->request = $request;
        $this->responses = $responses;
    }

    public function request(): RequestEntry
    {
        if (!isset($this->request)) $this->compute();

        return $this->request;
    }

    /**
     * @return \Ark4ne\OpenApi\Documentation\ResponseEntry[]
     */
    public function response(): array
    {
        if (!isset($this->responses)) $this->compute();

        return $this->responses;
    }

    /**
     * @param array{by: string, regex: string}|callable $config
     *
     * @return string|null
     */
    protected function resolveGroup(null|array|callable $config): ?string
    {
        if (null === $config) {
            return null;
        }

        if (is_callable($config)) {
            return $config($this);
        }

        $by = match ($config['by'] ?? null) {
            'uri' => $this->getRouteUri(),
            'name' => $this->getRouteName(),
            'controller' => $this->getControllerClass(),
            default => null
        };

        if ($by && preg_match($config['regex'], $by, $match)) {
            return $match[1];
        }

        return null;
    }
}
