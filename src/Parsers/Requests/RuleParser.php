<?php

namespace Ark4ne\OpenApi\Parsers\Requests;

use Ark4ne\OpenApi\Documentation\Request\Parameter;
use Ark4ne\OpenApi\Parsers\Requests\Concerns\Rules\CommonRules;
use Ark4ne\OpenApi\Parsers\Requests\Concerns\Rules\CustomRules;
use Ark4ne\OpenApi\Support\Facades\Logger;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;

class RuleParser
{
    use CustomRules, CommonRules;

    /**
     * @param \Ark4ne\OpenApi\Documentation\Request\Parameter $parameter
     * @param array{rule: string|Rule, parameters:string[]}[] $rules
     */
    public function __construct(
        protected Parameter $parameter,
        protected array $rules
    ) {
    }

    public function parse(): Parameter
    {
        foreach ($this->rules as $entry) {
            $rule = $entry['rule'];
            $parameters = $entry['parameters'];

            if ($rule instanceof ValidationRule || $rule instanceof Rule) {
                $this->parseCustomRules($rule, $parameters);
            } elseif (method_exists($this, "parse$rule")) {
                $this->{"parse$rule"}($parameters);
            } else {
                Logger::warn("No parser for Rule $rule.");
            }
        }

        return $this->parameter;
    }
}
