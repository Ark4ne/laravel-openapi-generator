<?php

namespace Ark4ne\OpenApi\Parsers\Requests\Concerns\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;

trait CustomRules
{
    public function parseCustomRules(ValidationRule | Rule $rule, array $parameters)
    {
        foreach (config('openapi.parsers.rules') as $ruleClass => $parserClass) {
            if ($rule instanceof $ruleClass) {
                app()->make($parserClass)->parse($this->parameter, $rule, $parameters, $this->rules);
            }
        }
    }
}
