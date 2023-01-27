<?php

namespace Test\Unit\Concerns;

use Ark4ne\OpenApi\Documentation\Request\Parameter;
use Ark4ne\OpenApi\Parsers\Requests\RuleParser;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Info;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Operation;
use GoldSpecDigital\ObjectOrientedOAS\Objects\PathItem;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Response;
use GoldSpecDigital\ObjectOrientedOAS\OpenApi;
use Test\Concerns\AssertOpenApi;

trait AssertRuleRequest
{
    use AssertOpenApi;

    public function assertParameter(Parameter $parameter, string $type): void
    {
        $info = Info::create()
            ->title('-')
            ->version('-')
            ->description('-');

        $openapi = OpenApi::create()
            ->openapi(OpenApi::OPENAPI_3_0_2)
            ->info($info)
            ->paths(PathItem::create()
                ->route('/')
                ->operations(Operation::get()
                    ->summary('-')
                    ->description('-')
                    ->parameters($parameter->oasParameters($type))
                    ->responses(Response::ok())
                ));

        $this->assertOpenapiArray($openapi->toArray());
    }

    private static function rule(string $rule, array $params = [])
    {
        return [
            'rule' => $rule,
            'parameters' => $params
        ];
    }

    /**
     * @param array<array{0: string, 1?: string[]}> $rules
     *
     * @return \Ark4ne\OpenApi\Parsers\Requests\RuleParser
     */
    private static function parser(array $rules)
    {
        return new RuleParser(new Parameter(uniqid('test', false)), array_map(
            static fn($rule) => self::rule($rule[0], $rule[1] ?? []),
            $rules
        ));
    }
}
