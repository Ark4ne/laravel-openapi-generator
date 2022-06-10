<?php

namespace Ark4ne\OpenApi\Contracts;

use Ark4ne\OpenApi\Documentation\Request\Parameter;
use Illuminate\Contracts\Validation\Rule;

interface CustomRuleParser
{
    /**
     * @param \Ark4ne\OpenApi\Documentation\Request\Parameter $parameter
     * @param \Illuminate\Contracts\Validation\Rule           $rule
     * @param string[]                                        $parameters
     * @param array{rule: string|Rule, parameters:string[]}[] $rules
     *
     * @return void
     */
    public function parse(Parameter $parameter, Rule $rule, array $parameters, array $rules): void;
}
