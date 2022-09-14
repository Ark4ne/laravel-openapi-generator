<?php

namespace Ark4ne\OpenApi\Parsers\Rules;

use Ark4ne\OpenApi\Contracts\CustomRuleParser;
use Ark4ne\OpenApi\Documentation\Request\Component;
use Ark4ne\OpenApi\Documentation\Request\Parameter;
use Ark4ne\OpenApi\Support\Reflection;
use Illuminate\Contracts\Validation\Rule;

class IncludesRuleParsers implements CustomRuleParser
{
    /**
     * @param \Ark4ne\OpenApi\Documentation\Request\Parameter $parameter
     * @param \Illuminate\Contracts\Validation\Rule           $rule
     * @param string[]                                        $parameters
     * @param array{rule: string|Rule, parameters:string[]}[] $rules
     *
     * @return void
     */
    public function parse(Parameter $parameter, Rule $rule, array $parameters, array $rules): void
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

        $this->schemaToRef($schema);

        $parameter->string()
            ->example($example = $this->example($schema))
            ->description($this->description($this->schemaToRef($schema), $example));
    }

    protected function getType(mixed $schema): string
    {
        if (str_contains($schema->type, 'fake')) {
            // todo suggest implement toType
            return (new \ReflectionClass($schema->for))->getShortName();
        }

        return $schema->type;
    }

    protected function schemaToRef(mixed $schema): string
    {
        $ref = 'include-' . $this->getType($schema);

        if (Component::has($ref, Component::SCOPE_SCHEMAS)) {
            return Component::get($ref, Component::SCOPE_SCHEMAS)?->ref();
        }

        $component = Component::create($ref, Component::SCOPE_SCHEMAS);

        $param = (new Parameter($ref))
            ->typeDescription($this->description($ref, $this->example($schema)))
            ->object()
            ->properties(
                ...collect($schema->relationships)
                ->map(function (mixed $schema, string $name) {
                    $property = new Parameter($name);
                    $component = $this->schemaToRef($schema);

                    return $property->ref($component);
                })
                ->all()
            );

        $component->object($param);

        return $component->ref();
    }

    protected function example(mixed $schema): string
    {
        $include = '';
        if (!empty($schema->relationships)) {
            $relations = array_keys($schema->relationships);
            $relations = array_slice($relations, 0, 2);

            $include = implode(',', $relations);

            foreach ($relations as $relation) {
                if (!empty($schema->relationships[$relation]->relationships)) {
                    $sub = array_keys($schema->relationships[$relation]->relationships);
                    $include .= ",$relation.{$sub[0]}";
                }
            }
        }

        return $include;
    }

    protected function description(string $ref, string $example): string
    {
        $description[] = "**string**: resource to includes, separate by comma, linked by dot notation.";

        if ($example) {
            $description[] = "example: $example";
        }

        if ($ref) {
            $description[] = "schema: $ref";
        }

        return implode("  \n", $description);
    }
}
