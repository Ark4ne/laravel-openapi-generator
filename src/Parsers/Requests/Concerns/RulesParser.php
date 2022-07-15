<?php

namespace Ark4ne\OpenApi\Parsers\Requests\Concerns;

use Ark4ne\OpenApi\Contracts\Entry;
use Ark4ne\OpenApi\Descriptors\Requests\Rule;
use Ark4ne\OpenApi\Documentation\Request\Parameter;
use Ark4ne\OpenApi\Parsers\Requests\RuleParser;
use Ark4ne\OpenApi\Support\Trans;
use Closure;
use Illuminate\Contracts\Validation\Rule as ValidationRule;
use Illuminate\Validation\ValidationRuleParser;

use function str_contains;

trait RulesParser
{
    /**
     * @param \Ark4ne\OpenApi\Contracts\Entry $entry
     * @param iterable                        $rules
     *
     * @return \Illuminate\Support\Collection&iterable<Parameter>
     */
    protected function rules(Entry $entry, iterable $rules): iterable
    {
        return collect($rules)->map(
            fn($rule, $attribute) => $this->parseRule($entry, new Parameter($attribute), $rule)
        );
    }

    protected function parseRule(Entry $entry, Parameter $parameter, mixed $rule): Parameter
    {
        $description = '';

        if ($rule instanceof Rule) {
            $description = $rule->description;
            $rule = $rule->rule;
        }

        // TODO check typeDescription
        $parameter->typeDescription(Trans::get([
            "openapi.requests.parameters.custom.{$entry->getName()}.$parameter->name",
            "openapi.requests.parameters.$parameter->name",
        ], default: $description));

        return (new RuleParser($parameter, $this->prepareRules($rule)))->parse();
    }

    /**
     * @param string|array|ValidationRule|\Closure                      $ruleRaw
     * @param array{rule: string|ValidationRule, parameters:string[]}[] $rules
     *
     * @return array{rule: string|ValidationRule, parameters:string[]}[]
     */
    protected function prepareRules(string|array|ValidationRule|Closure $ruleRaw, array &$rules = []): array
    {
        if ($ruleRaw instanceof Closure) {
            return $rules;
        }

        if (is_string($ruleRaw) && strpos($ruleRaw, '|')) {
            $ruleRaw = explode('|', $ruleRaw);
        }

        if (is_array($ruleRaw)) {
            foreach ($ruleRaw as $rule) {
                $this->prepareRules($rule, $rules);
            }
            return $rules;
        }

        [$rule, $parameters] = ValidationRuleParser::parse($ruleRaw);

        if (empty($rule)) {
            return $rules;
        }

        if (is_string($rule) && str_contains($rule, '|')) {
            return $this->prepareRules(explode('|', $rule), $rules);
        }

        $rules[] = ['rule' => $rule, 'parameters' => $parameters];

        return $rules;
    }
}
