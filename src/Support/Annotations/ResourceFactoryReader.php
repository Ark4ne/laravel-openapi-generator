<?php

namespace Ark4ne\OpenApi\Support\Annotations;

use Ark4ne\OpenApi\Annotations\ResourceFactory;
use Ark4ne\OpenApi\Support\Reflection;

class ResourceFactoryReader
{
    private ResourceFactory $attribute;

    public function __construct(private string $resourceClass)
    {

    }

    public function getResourceFactory(): ?ResourceFactory
    {
        if (isset($this->attribute)) {
            return $this->attribute;
        }

        $reflection = Reflection::reflection($this->resourceClass);
        $attributes = $reflection->getAttributes(ResourceFactory::class);

        if (empty($attributes)) {
            return null;
        }

        $attribute = $attributes[0];

        return $this->attribute = $attribute->newInstance();
    }

    public function hasResourceFactory(): bool
    {
        return $this->getResourceFactory() !== null;
    }

    public function createFromResourceFactory(int $count = 1): mixed
    {
        $resourceFactory = $this->getResourceFactory();

        if (!$resourceFactory) {
            throw new \InvalidArgumentException("No custom factory found for {$this->resourceClass}");
        }

        $factoryClass = $resourceFactory->factory;
        $method = $resourceFactory->method ?? 'create';
        $parameters = $resourceFactory->parameters;

        if (!class_exists($factoryClass)) {
            throw new \InvalidArgumentException("Factory class {$factoryClass} not found");
        }

        if (!method_exists($factoryClass, $method)) {
            throw new \InvalidArgumentException("Method {$method} not found in {$factoryClass}");
        }

        $factory = new $factoryClass();

        if ($count === 1) {
            return $factory->$method($parameters);
        }

        return array_map(
            fn() => $factory->$method($parameters),
            range(1, $count)
        );
    }
}
