<?php

namespace Ark4ne\OpenApi\Documentation;

use Ark4ne\OpenApi\Contracts\Entry;
use Ark4ne\OpenApi\Support\Reflection;
use Illuminate\Foundation\Http\FormRequest;
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
     * @return array<string>
     */
    public function getPathParameters(): array
    {
        if (isset($this->parameters)) {
            return $this->parameters;
        }

        /** @var \Symfony\Component\Routing\CompiledRoute $compileRoute */
        $compileRoute = Reflection::call($this->route, 'compileRoute');

        return $this->parameters = $compileRoute->getPathVariables();
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

    public function getResponseClass(): ?string
    {
        if (isset($this->response)) {
            return $this->response;
        }

        $class = new ReflectionClass($this->getController());
        $action = $this->getAction();

        $method = $class->getMethod($action);

        if ($return = $method->getReturnType()) {
            return $this->response = Reflection::parseTypeHint($return);
        }

        return null;
    }

    /**
     * @return null|class-string<FormRequest>
     */
    public function getRequestClass(): ?string
    {
        if (isset($this->request)) {
            return $this->request;
        }

        foreach ($this->route->signatureParameters() as $parameter) {
            if (!$parameter->hasType() || !($type = $parameter->getType())) {
                continue;
            }

            if ($type = Reflection::parseTypeHint($type, FormRequest::class)) {
                return $this->request = $type;
            }
        }

        return null;
    }
}
