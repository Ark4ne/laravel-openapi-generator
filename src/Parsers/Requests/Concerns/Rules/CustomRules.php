<?php

namespace Ark4ne\OpenApi\Parsers\Requests\Concerns\Rules;

use Ark4ne\OpenApi\Documentation\Request\Body\Parameter;
use Illuminate\Contracts\Validation\Rule;

trait CustomRules
{
    public function parseCustomRules(Parameter $parameter, Rule $rule, array $parameters)
    {
        foreach (config('openapi.parsers.rules') as $ruleClass => $parserClass) {
            if ($rule instanceof $ruleClass) {
                app()->make($parserClass)->parse($parameter, $rule, $parameters);
            }
        }
    }
}
