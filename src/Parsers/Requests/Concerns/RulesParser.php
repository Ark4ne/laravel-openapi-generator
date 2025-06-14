<?php

namespace Ark4ne\OpenApi\Parsers\Requests\Concerns;

use Ark4ne\OpenApi\Contracts\Entry;
use Ark4ne\OpenApi\Descriptors\Requests\Rule;
use Ark4ne\OpenApi\Documentation\Request\Parameter;
use Ark4ne\OpenApi\Parsers\Requests\RuleParser;
use Ark4ne\OpenApi\Support\ClassHelper;
use Ark4ne\OpenApi\Support\Trans;
use Closure;
use Illuminate\Contracts\Validation\Rule as DeprecatedValidationRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\ValidationRuleParser;
use Stringable;

use function str_starts_with;
use function str_contains;

trait RulesParser
{
    /**
     * @param \Ark4ne\OpenApi\Contracts\Entry $entry
     * @param iterable $rules
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
            "openapi.requests.parameters.custom.{$entry->getRouteName()}.$parameter->name",
            "openapi.requests.parameters.$parameter->name",
        ], default: $description));

        return (new RuleParser($parameter, $this->prepareRules($rule)))->parse();
    }

    /**
     * @param string|array|DeprecatedValidationRule|ValidationRule|\Closure $ruleRaw
     * @param array{rule: string|DeprecatedValidationRule, parameters:string[]}[] $rules
     *
     * @return array{rule: string|DeprecatedValidationRule, parameters:string[]}[]
     */
    protected function prepareRules(mixed $ruleRaw, array &$rules = []): array
    {
        if ($ruleRaw instanceof Closure) {
            return $rules;
        }

        if (class_exists(Stringable::class) && $ruleRaw instanceof Stringable) {
            $ruleRaw = $ruleRaw->toString();
        }

        if ($this->shouldBeSlit($ruleRaw)) {
            $ruleRaw = explode('|', $ruleRaw);
        }

        if (is_object($ruleRaw) && method_exists($ruleRaw, 'toArray')) {
            $ruleRaw = $ruleRaw->toArray();
        }

        if (is_array($ruleRaw)) {
            foreach ($ruleRaw as $rule) {
                $this->prepareRules($rule, $rules);
            }
            return $rules;
        }

        if (ClassHelper::isInstanceOf($ruleRaw, ValidationRule::class) || ClassHelper::isInstanceOf($ruleRaw, DeprecatedValidationRule::class)) {
            $rules[] = ['rule' => $ruleRaw, 'parameters' => []];

            return $rules;
        }

        [$rule, $parameters] = ValidationRuleParser::parse($ruleRaw);

        if (empty($rule)) {
            return $rules;
        }

        if ($this->shouldBeSlit($rule)) {
            return $this->prepareRules(explode('|', $rule), $rules);
        }

        $rules[] = ['rule' => $rule, 'parameters' => $parameters];

        return $rules;
    }

    private function shouldBeSlit($rule): bool
    {
        return is_string($rule) && !str_starts_with($rule, 'regex:') && str_contains($rule, '|');
    }
}
