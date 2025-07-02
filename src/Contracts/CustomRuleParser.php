<?php

namespace Ark4ne\OpenApi\Contracts;

use Ark4ne\OpenApi\Documentation\Request\Parameter;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * @deprecated rename to CustomRuleParserContract
 */
interface CustomRuleParser
{
    /**
     * @param \Ark4ne\OpenApi\Documentation\Request\Parameter $parameter
     * @param ValidationRule|Rule                             $rule
     * @param string[]                                        $parameters
     * @param array{rule: string|Rule, parameters:string[]}[] $rules
     *
     * @return void
     */
    public function parse(Parameter $parameter, ValidationRule|Rule $rule, array $parameters, array $rules): void;
}
