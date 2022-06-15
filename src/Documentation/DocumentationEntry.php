<?php

namespace Ark4ne\OpenApi\Documentation;

use Ark4ne\OpenApi\Contracts\Entry;
use Ark4ne\OpenApi\Support\Reflection;
use Ark4ne\OpenApi\Support\Reflection\Type;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;

class DocumentationEntry implements Entry
{
    protected string $name;
    protected string $uri;
    /** @var array<string> */
    protected array $parameters;
    protected mixed $controller;
    protected string $action;
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

    public function getUri(): string
    {
        return $this->uri ??= $this->route->uri();
    }

    public function getName(): string
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
    public function getPathParameters(): array
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

    /**
     * @return \Ark4ne\OpenApi\Support\Reflection\Type<Response, mixed>
     */
    public function getResponseClass(): Reflection\Type
    {
        if (isset($this->responseClass)) {
            return $this->responseClass;
        }

        $method = Reflection::method($this->getControllerClass(), $this->getAction());

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

        $method = Reflection::method($this->getControllerClass(), $this->getAction());

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
}
