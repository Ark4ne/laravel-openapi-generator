<?php

namespace Ark4ne\OpenApi\Documentation\Request;

use Illuminate\Support\Arr;
use GoldSpecDigital\ObjectOrientedOAS\Objects\{MediaType, Parameter as OASParameter, RequestBody, Schema};

class Parameters
{
    /**
     * @param iterable<array-key, \Ark4ne\OpenApi\Documentation\Request\Parameter> $parameters
     */
    public function __construct(
        protected iterable $parameters
    ) {
    }

    public function convert(string $type)
    {
        switch ($type) {
            case OASParameter::IN_COOKIE:
            case OASParameter::IN_HEADER:
            case OASParameter::IN_PATH:
            case OASParameter::IN_QUERY:
                return collect($this->parameters)
                    ->map(static fn(Parameter $param) => $param->oasParameters($type))
                    ->values()
                    ->all();
            case 'body':
                /*
                $params = collect($this->parameters)
                    ->map(static fn(Parameter $param) => [$param->name, $param])
                    ->all();

                $params = array_combine(
                    array_column($params, 0),
                    array_column($params, 1)
                );

                $params = Arr::undot($params);
                */

                $schema = Schema::create()->properties(...collect($this->parameters)
                    ->map(static fn(Parameter $param) => $param->oasSchema())
                    ->values()
                    ->all());

                $content = MediaType::json()->schema($schema);

                return RequestBody::create()->content($content);
        }
    }
}
