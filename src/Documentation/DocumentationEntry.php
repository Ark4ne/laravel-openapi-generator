<?php

namespace Ark4ne\OpenApi\Documentation;

use Ark4ne\OpenApi\Contracts\Entry;
use Ark4ne\OpenApi\Support\ArrayInsensitive;
use Ark4ne\OpenApi\Support\Config;
use Ark4ne\OpenApi\Support\Reflection;
use Ark4ne\OpenApi\Support\Reflection\Type;
use Ark4ne\OpenApi\Support\Trans;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
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
    protected null|Reflection\Type $responseClass;

    protected RequestEntry $request;
    protected mixed $response;

    public function __construct(
        protected Route $route
    ) {
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
     * @throws \ReflectionException
     * @return array<string, null|string>
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
     * @return \Ark4ne\OpenApi\Support\Reflection\Type<Response, mixed>
     */
    public function getResponseClass(): Reflection\Type
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

    public function request(): RequestEntry
    {
        return $this->request ??= $this->parse(config('openapi.parsers.requests'), $this->getRequestClass());
    }

    public function response(): ResponseEntry
    {
        return $this->response ??= $this->parse(config('openapi.parsers.responses'), $this->getResponseClass());
    }

    /**
     * @param array<class-string> $parsers
     * @param null|Type           $element
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @return mixed
     */
    protected function parse(array $parsers, ?Reflection\Type $element): mixed
    {
        if ($element === null) {
            return null;
        }

        foreach ($parsers as $for => $parser) {
            if (is_a($element->getType(), $for, true)) {
                return app()->make($parser)->parse($element, $this);
            }
        }

        throw new \Exception("TODO: Can't parse " . $element->getType());
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
