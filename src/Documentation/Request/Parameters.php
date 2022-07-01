<?php

namespace Ark4ne\OpenApi\Documentation\Request;

use InvalidArgumentException;
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

    /**
     * @param string               $type
     * @param null|string|string[] $format
     * @param bool                 $flat
     *
     * @return array<OASParameter>|\GoldSpecDigital\ObjectOrientedOAS\Objects\RequestBody
     */
    public function convert(string $type, null|string|array $format = null, bool $flat = false): array|RequestBody
    {
        switch ($type) {
            case OASParameter::IN_COOKIE:
            case OASParameter::IN_HEADER:
            case OASParameter::IN_PATH:
                return collect($this->parameters)
                    ->map(static fn(Parameter $param) => $param->oasParameters($type))
                    ->values()
                    ->all();
            case OASParameter::IN_QUERY:
                $params = $flat ? $this->flat() : $this->undot();

                return collect($params)
                    ->map(function (array|Parameter $param, $name) use ($type, $flat) {
                        if ($param instanceof Parameter) {
                            return ($flat ? $param->flat() : $param->undot())->oasParameters($type);
                        }

                        /** @var OASParameter $parameter */
                        $parameter = OASParameter::$type($name);

                        return $parameter->name($name)->schema($this->arrayToSchema($param, $name));
                    })
                    ->values()
                    ->all();
            case 'body':
                $params = $this->undot();

                $schema = Schema::create()->properties(...$this->arrayToProperties($params));

                return RequestBody::create()->content(Content::convert($schema, $format));
        }

        throw new InvalidArgumentException("unknown $type.");
    }

    /**
     * @return array<Parameter|array<Parameter>>
     */
    protected function undot(): array
    {
        $params = collect($this->parameters)
            ->map(static fn(Parameter $param) => [$param->name, $param])
            ->all();

        $params = array_combine(
            array_column($params, 0),
            array_column($params, 1)
        );

        return Arr::undot($params);
    }

    protected function flat(): array
    {
        $flat = static function ($array, $prepend = '') use (&$flat) {
            $results = [];

            foreach ($array as $key => $value) {
                $sub = $key === '*' ? '' : $key;
                $arrayKey = $prepend ? "{$prepend}[$sub]" : $sub;
                if (is_array($value) && !empty($value)) {
                    $results = array_merge($results, $flat($value, $arrayKey));
                } else {
                    $results[$arrayKey] = $value;
                }
            }

            return $results;
        };

        return $flat($this->undot());
    }

    /**
     * @param array<Parameter|array<Parameter>> $properties
     *
     * @return array<Schema>
     */
    protected function arrayToProperties(array $properties): array
    {
        return collect($properties)
            ->map(function (array|Parameter $param, $name) {
                if ($param instanceof Parameter) {
                    return $param->undot()->oasSchema();
                }

                return $this->arrayToSchema($param, $name);
            })
            ->values()
            ->all();
    }

    /**
     * @param array<Parameter|array<Parameter>> $params
     * @param string                            $name
     *
     * @return \GoldSpecDigital\ObjectOrientedOAS\Objects\Schema
     */
    protected function arrayToSchema(array $params, string $name): Schema
    {
        if (array_keys($params) === ['*']) {
            if (is_array($params['*'])) {
                $items = Schema::object($name)->properties(...$this->arrayToProperties($params['*']));
            } else {
                $items = $params['*']->oasSchema();
            }

            return Schema::array($name)->items($items);
        }

        return Schema::object($name)->properties(
            ...$this->arrayToProperties($params)
        );
    }
}
