<?php

namespace Ark4ne\OpenApi\Parsers\Requests;

use Ark4ne\OpenApi\Documentation\Request\Parameter;
use Ark4ne\OpenApi\Parsers\Requests\Concerns\Rules\CommonRules;
use Ark4ne\OpenApi\Parsers\Requests\Concerns\Rules\CustomRules;
use Illuminate\Contracts\Validation\Rule;

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

            if ($rule instanceof Rule) {
                $this->parseCustomRules($rule, $parameters);
            } else {
                $this->{"parse$rule"}($parameters);
            }
        }

        return $this->parameter;
    }
}
