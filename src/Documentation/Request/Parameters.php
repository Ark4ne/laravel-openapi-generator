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
     *
     * @return array<OASParameter>|\GoldSpecDigital\ObjectOrientedOAS\Objects\RequestBody
     */
    public function convert(string $type, null|string|array $format = null): array|RequestBody
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
                $params = $this->undot();

                return collect($params)
                    ->map(function (array|Parameter $param, $name) use ($type) {
                        if ($param instanceof Parameter) {
                            return $param->undot()->oasParameters($type);
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

                $mediaType = MediaType::MEDIA_TYPE_APPLICATION_JSON;
                $mediaTypes = [
                    MediaType::MEDIA_TYPE_APPLICATION_JSON,
                    MediaType::MEDIA_TYPE_TEXT_XML,
                    MediaType::MEDIA_TYPE_APPLICATION_X_WWW_FORM_URLENCODED
                ];

                foreach ($mediaTypes as $acceptable) {
                    if (in_array($acceptable, (array)$format, true)) {
                        $mediaType = $acceptable;
                        break;
                    }
                }

                $content = (new MediaType)
                    ->mediaType($mediaType)
                    ->schema($schema);

                return RequestBody::create()->content($content);
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
