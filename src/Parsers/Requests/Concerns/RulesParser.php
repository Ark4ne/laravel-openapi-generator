<?php

namespace Ark4ne\OpenApi\Parsers\Requests\Concerns;

use Ark4ne\OpenApi\Documentation\Request\Parameter;
use Ark4ne\OpenApi\Parsers\Requests\RuleParser;
use Closure;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Validation\ValidationRuleParser;

use function str_contains;

trait RulesParser
{
    /**
     * @param iterable $rules
     *
     * @return \Illuminate\Support\Collection&iterable<Parameter>
     */
    protected function rules(iterable $rules): iterable
    {
        return collect($rules)->map(
            fn($rule, $attribute) => (new RuleParser(new Parameter($attribute), $this->prepareRules($rule)))->parse()
        );
    }

    /**
     * @param string|array|\Illuminate\Contracts\Validation\Rule|\Closure $ruleRaw
     * @param array{rule: string|Rule, parameters:string[]}[]             $rules
     *
     * @return array{rule: string|Rule, parameters:string[]}[]
     */
    protected function prepareRules(string|array|Rule|Closure $ruleRaw, array &$rules = []): array
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
