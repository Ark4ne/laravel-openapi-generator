<?php

namespace Ark4ne\OpenApi\OAS\Objects;

use GoldSpecDigital\ObjectOrientedOAS\Objects\Schema as BaseSchema;
use GoldSpecDigital\ObjectOrientedOAS\Objects\SchemaComposition;
use GoldSpecDigital\ObjectOrientedOAS\Utilities\Arr;

class Schema extends BaseSchema
{
    private null|SchemaComposition $composition = null;

    public function composition(SchemaComposition $composition): self
    {
        $instance = clone $this;

        $instance->composition = $composition;

        return $instance;
    }

    /**
     * @return array<string, mixed>
     */
    protected function generate(): array
    {
        $properties = [];
        foreach ($this->properties ?? [] as $property) {
            $properties[$property->objectId] = $property->toArray();
        }

        return Arr::filter([
            ...($this->composition?->toArray() ?? []),
            'title' => $this->title,
            'description' => $this->description,
            'enum' => $this->enum,
            'default' => $this->default,
            'format' => $this->format,
            'type' => $this->type,
            'items' => $this->items,
            'maxItems' => $this->maxItems,
            'minItems' => $this->minItems,
            'uniqueItems' => $this->uniqueItems,
            'pattern' => $this->pattern,
            'maxLength' => $this->maxLength,
            'minLength' => $this->minLength,
            'maximum' => $this->maximum,
            'exclusiveMaximum' => $this->exclusiveMaximum,
            'minimum' => $this->minimum,
            'exclusiveMinimum' => $this->exclusiveMinimum,
            'multipleOf' => $this->multipleOf,
            'required' => $this->required,
            'properties' => $properties ?: null,
            'additionalProperties' => $this->additionalProperties,
            'maxProperties' => $this->maxProperties,
            'minProperties' => $this->minProperties,
            'nullable' => $this->nullable,
            'discriminator' => $this->discriminator,
            'readOnly' => $this->readOnly,
            'writeOnly' => $this->writeOnly,
            'xml' => $this->xml,
            'externalDocs' => $this->externalDocs,
            'example' => $this->example,
            'deprecated' => $this->deprecated,
        ]);
    }
}