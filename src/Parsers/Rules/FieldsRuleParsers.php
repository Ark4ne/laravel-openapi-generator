<?php

namespace Ark4ne\OpenApi\Parsers\Rules;

use Ark4ne\OpenApi\Contracts\CustomRuleParserContract;
use Ark4ne\OpenApi\Documentation\Request\Component;
use Ark4ne\OpenApi\Documentation\Request\Parameter;
use Ark4ne\OpenApi\Support\Reflection;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;

class FieldsRuleParsers implements CustomRuleParserContract
{
    /**
     * @param \Ark4ne\OpenApi\Documentation\Request\Parameter $parameter
     * @param \Illuminate\Contracts\Validation\Rule           $rule
     * @param string[]                                        $parameters
     * @param array{rule: string|Rule, parameters:string[]}[] $rules
     *
     * @return void
     */
    public function parse(Parameter $parameter, ValidationRule|Rule $rule, array $parameters, array $rules): void
    {
        try {
            $resource = Reflection::read($rule, 'resource');
        } catch (\Throwable $e) {
            // TODO exception handler
            return;
        }

        if (!is_subclass_of($resource, \Ark4ne\JsonApi\Resources\JsonApiResource::class)) {
            return;
        }

        $schema = $resource::schema();

        $parameter->string()->ref($this->schemaToRef($schema));
    }

    protected function schemaToRef(mixed $schema): string
    {
        $resources = [];

        foreach ($schema->relationships as $name => $relationship) {
            $resources = $this->extractSchemas($name, $relationship, $resources);
        }

        $ref = 'fields:' . implode('-', array_keys($resources));

        if (Component::has($ref, Component::SCOPE_SCHEMAS)) {
            return Component::get($ref, Component::SCOPE_SCHEMAS)?->ref();
        }

        $param = (new Parameter($ref))
            ->object()
            ->properties(
                ...collect($resources)
                ->map(fn (mixed $fields, string $name) => (new Parameter($name))->enum($fields))
                ->all()
            );

        $component = Component::create($ref, Component::SCOPE_SCHEMAS);

        $component->object($param);

        return $component->ref();
    }

    protected function getType(mixed $schema, string $default): string
    {
        if (str_contains($schema->type, 'fake')) {
            // todo suggest implement toType
            return $default;
        }

        return $schema->type;
    }

    protected function extractSchemas(string $fromName, mixed $schema, array $resources = []): array
    {
        $type = $this->getType($schema, $fromName);

        if (!$type || isset($resources[$type])) {
            return $resources;
        }

        $resources[$type] = $schema->fields;

        foreach ($schema->relationships as $name => $relationship) {
            $resources = $this->extractSchemas($name, $relationship, $resources);
        }

        return $resources;
    }
}
