<?php

namespace Ark4ne\OpenApi\Support\Annotations;

use Ark4ne\OpenApi\Attributes as OAttributes;
use Ark4ne\OpenApi\Support\Reflection;

class ResponseAttributeReader
{
    private OAttributes\Response $attribute;

    public function __construct(
        private readonly string $controllerClass,
        private readonly string $methodName,
    ) {
    }

    public function getResponseAttribute(): ?OAttributes\Response
    {
        if (isset($this->attribute)) {
            return $this->attribute;
        }

        $method = Reflection::method($this->controllerClass, $this->methodName);
        $attributes = $method->getAttributes(OAttributes\Response::class);

        if (empty($attributes)) {
            return null;
        }

        $attribute = $attributes[0];

        return $this->attribute = $attribute->newInstance();
    }

    public function hasResponseAttribute(): bool
    {
        return $this->getResponseAttribute() !== null;
    }
}
