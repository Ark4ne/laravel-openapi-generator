<?php

namespace Ark4ne\OpenApi\Documentation;

use Ark4ne\OpenApi\Contracts\Entry;
use Ark4ne\OpenApi\Support\Reflection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use ReflectionClass;

class DocumentationEntry implements Entry
{
    protected string $name;
    protected string $uri;
    /** @var array<string> */
    protected array $parameters;
    protected mixed $controller;
    protected string $action;
    protected null|string $request;
    protected null|string $response;

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
     * @return class-string<Response>
     */
    public function getResponseClass(): string
    {
        if (isset($this->response)) {
            return $this->response;
        }

        $class = new ReflectionClass($this->getController());
        $action = $this->getAction();

        $method = $class->getMethod($action);

        if (($return = $method->getReturnType()) && $response = Reflection::parseTypeHint($return)) {
            return $this->response = $response;
        }

        return Response::class;
    }

    /**
     * @return class-string<Request>
     */
    public function getRequestClass(): string
    {
        if (isset($this->request)) {
            return $this->request;
        }

        foreach ($this->route->signatureParameters() as $parameter) {
            if (!$parameter->hasType() || !($type = $parameter->getType())) {
                continue;
            }

            if ($type = Reflection::parseTypeHint($type, Request::class)) {
                return $this->request = $type;
            }
        }

        return Request::class;
    }
}
