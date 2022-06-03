<?php

namespace Ark4ne\OpenApi\Parsers\Requests\Concerns;

use Ark4ne\OpenApi\Parsers\Requests\Concerns\Rules\CommonRules;
use Ark4ne\OpenApi\Parsers\Requests\Concerns\Rules\CustomRules;
use Ark4ne\OpenApi\Documentation\Request\Parameter;
use Closure;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Validation\ValidationRuleParser;

use function str_contains;

trait RulesParser
{
    use CustomRules, CommonRules;

    /**
     * @param iterable $rules
     *
     * @return \Illuminate\Support\Collection&iterable<Parameter>
     */
    protected function rules(iterable $rules): iterable
    {
        return collect($rules)->map(
            fn($rule, $attribute) => $this->rule(new Parameter($attribute), $rule)
        );
    }

    /**
     * @param \Ark4ne\OpenApi\Documentation\Request\Parameter                    $parameter
     * @param string|array<mixed>|\Illuminate\Contracts\Validation\Rule|\Closure $ruleRaw
     *
     * @return \Ark4ne\OpenApi\Documentation\Request\Parameter
     */
    protected function rule(Parameter $parameter, string|array|Rule|Closure $ruleRaw): Parameter
    {
        if ($ruleRaw instanceof Closure) {
            return $parameter;
        }

        if (is_string($ruleRaw) && strpos($ruleRaw, '|')) {
            $ruleRaw = explode('|', $ruleRaw);
        }

        if (is_array($ruleRaw)) {
            foreach ($ruleRaw as $rule) {
                $this->rule($parameter, $rule);
            }
            return $parameter;
        }

        [$rule, $parameters] = ValidationRuleParser::parse($ruleRaw);

        if (empty($rule)) {
            return $parameter;
        }

        if (is_string($rule) && str_contains($rule, '|')) {
            return $this->rule($parameter, explode('|', $rule));
        }

        if ($rule instanceof Rule) {
            $this->parseCustomRules($parameter, $rule, $parameters);

            return $parameter;
        }

        $this->{"parse$rule"}($parameter, $parameters);

        return $parameter;
    }
}
