<?php

namespace Ark4ne\OpenApi\Parsers\Responses\Concerns;

use Ark4ne\JsonApi\Descriptors\Describer;
use Ark4ne\JsonApi\Descriptors\Relations\Relation;
use Ark4ne\JsonApi\Descriptors\Relations\RelationMany;
use Ark4ne\JsonApi\Descriptors\Values\ValueStruct;
use Ark4ne\JsonApi\Resources\Concerns\PrepareData;
use Ark4ne\JsonApi\Resources\Relationship;
use Ark4ne\JsonApi\Support\Values;
use Ark4ne\OpenApi\Support\Facades\Logger;
use Ark4ne\OpenApi\Support\Fake;
use Ark4ne\OpenApi\Support\Reflection;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

trait JAResource
{
    use ValueType;
    use Resource;

    protected function generateStructure($instance)
    {
        $request = request();

        return [
            'data' => [
                    'id' => 'int|string',
                    'type' => $this->getType($instance::class),
                    'attributes' => $this->getSamples($instance, 'toAttributes', $request),
                    'relationships' => collect($this->mergeValues(Reflection::hasMethod($instance, 'toRelationships')
                        ? Reflection::call($instance, 'toRelationships', $request)
                        : []
                    ))
                        ->mapWithKeys(function ($relationship, $name) use ($instance) {
                            $name = Describer::retrieveName($relationship, $name);

                            return [$name => $this->mapRelationships($instance, $relationship, $name)];
                        })
                        ->all()
                ] + array_filter([
                    'meta' => $this->getSamples($instance, 'toResourceMeta', $request),
                    'links' => $this->getSamples($instance, 'toLinks', $request),
                ]),
        ];
    }

    protected function mapRelationships($instance, $relationship)
    {
        if ($relationship instanceof Relationship) {
            $resource = $relationship->getResource();
        } elseif ($relationship instanceof Relation) {
            $resource = $relationship->related();
        } else {
            throw new \Exception('Unsupported relation type: ' . $relationship::class);
        }
        $class = Reflection::reflection($resource);
        $isCollectionClass = is_subclass_of($resource, ResourceCollection::class);
        $isCollection = $isCollectionClass
            || ($relationship instanceof Relationship && Reflection::read($relationship, 'asCollection'))
            || $relationship instanceof RelationMany;

        $sample = [
            'id' => 'int|string',
        ];

        if ($isCollectionClass && ($collects = $this->getResourceFromCollection(
                $class->newInstanceWithoutConstructor(),
                Reflection::tryParseGeneric($class, 'extends'))
            )) {
            $sample['type'] = $this->getType($collects);
        } else {
            $sample['type'] = $this->getType($resource);
        }

        $data = $isCollection
            ? [$sample, $sample]
            : $sample;

        if ($relationshipLinks = Reflection::read($relationship, 'links')) {
            try {
                $links = $relationshipLinks(new Fake);
            } catch (\Throwable $e) {
                Logger::warn([
                    'Fail to generate structure for toLinks on json-api-resource ' . $instance::class,
                    $e->getMessage()
                ]);
                Logger::notice('Use empty array instead');
            }
        }

        if ($relationshipMeta = Reflection::read($relationship, 'meta')) {
            try {
                $meta = $relationshipMeta(new Fake);
            } catch (\Throwable $e) {
                Logger::warn([
                    'Fail to generate structure for toMeta on json-api-resource ' . $instance::class,
                    $e->getMessage()
                ]);
                Logger::notice('Use empty array instead');
            }
        }

        return [
                'data' => $data,
            ] + array_filter([
                'links' => $links ?? null,
                'meta' => $meta ?? null,
            ]);
    }

    protected function mergeResponseWithStructure(Response $response, array $structure)
    {
        $merge = static function ($data, $structure) {
            $filter = static fn($v) => $v !== null && $v !== '' && $v !== [];

            $basic = static fn($key, $structure, &$data) => $data[$key] = array_merge(
                $structure['data'][$key] ?? [],
                array_filter($data[$key] ?? [], $filter)
            );
            $basic('attributes', $structure, $data);
            $basic('meta', $structure, $data);
            $basic('links', $structure, $data);

            $data['relationships'] = array_merge(
                $structure['data']['relationships'] ?? [],
                array_filter($data['relationships'] ?? [])
            );

            $data['relationships'] = collect($data['relationships'])
                ->map(fn($value, $key) => array_merge($structure['data']['relationships'][$key] ?? [], $value))
                ->all();

            $data['id'] ??= $structure['id'];
            $data['type'] ??= $structure['type'];

            return array_filter($data, $filter);
        };

        $data = $response->getData(true);

        if (Arr::isAssoc($data['data'])) {
            $data['data'] = $merge($data['data'], $structure);
        } else {
            foreach ($data['data'] as &$datum) {
                $datum = $merge($datum, $structure);
            }
            unset($datum);
        }

        return $response->setData($data);
    }

    private function mergeValues($values)
    {
        static $mergeValues;

        if (!isset($mergeValues)) {
            // Available for json-api v1.3
            if (class_exists(Values::class)) {
                $mergeValues = static fn($values) => Values::mergeValues($values);
            } // Available for json-api v1.2
            elseif (trait_exists(PrepareData::class)) {
                $stub = new class {
                    use PrepareData;
                };

                $method = Reflection::method($stub, 'mergeValues');
                $method->setAccessible(true);

                $mergeValues = static fn($values) => $method->invoke($stub, $values);
            } // no merge values
            else {
                $mergeValues = static fn($values) => $values;
            }
        }

        return $mergeValues($values);
    }

    /**
     * @param                                                          $instance
     * @param string $method
     * @param callable(string $key, mixed $value):array<string, mixed> $map
     * @param                                                          $request
     *
     * @return array<string, mixed>
     */
    private function mapSamples($instance, string $method, callable $map, $request = null): array
    {
        $request ??= request();

        if (!Reflection::hasMethod($instance, $method)) {
            return [];
        }

        try {
            return collect($this->mergeValues(Reflection::call($instance, $method, $request) ?? []))
                ->mapWithKeys($map)
                ->all();
        } catch (\Throwable $e) {
            Logger::warn([
                "Fail to generate structure for $method on json-api-resource " . $instance::class,
                $e->getMessage()
            ]);
            Logger::notice('Use empty array instead');
            return [];
        }
    }

    /**
     * @param        $instance
     * @param string $method
     * @param        $request
     *
     * @return array<string, string>
     */
    private function getSamples($instance, string $method, $request = null): array
    {
        return $this->mapSamples(
            $instance,
            $method,
            fn($value, $key) => $this->mapValue($instance, $value, $key),
            $request
        );
    }

    private function mapValue($instance, $value, $key): array
    {
        $key = Describer::retrieveName($value, $key);

        if ($this->isBool($value)) {
            return [$key => 'bool'];
        }
        if ($this->isInt($value)) {
            return [$key => 'integer'];
        }
        if ($this->isFloat($value)) {
            return [$key => 'float'];
        }
        if ($this->isString($value)) {
            return [$key => 'string'];
        }
        if ($this->isArray($value)) {
            return [$key => 'array'];
        }
        if ($this->isDate($value)) {
            $sample = 'date';

            if ($this->isDescriber($value)) {
                $format = null;
                try {
                    $format = Reflection::read($value, 'format');
                } catch (\Throwable $e) {
                }

                if (!$format && class_exists(\Ark4ne\JsonApi\Support\Config::class)) {
                    $format = \Ark4ne\JsonApi\Support\Config::$date;
                }

                if (!$format) {
                    $format = config('jsonapi.describer.date');
                }

                if ($format) {
                    $sample = "date{{$format}}";
                }
            }

            return [$key => $sample];
        }
        if ($this->isStruct($value)) {
            /** @var ValueStruct $value */
            $attributes = ($value->retriever())($instance, $key);

            return [$key => collect($attributes)->flatMap(fn($value, $key) => $this->mapValue($instance, $value, $key))->all()];
        }
        if ($this->isEnum($value)) {
            $enum = self::describeEnum($this->getResourceClass(Reflection::reflection($instance)), $key);

            if ($enum) {
                $values = self::describeEnumValues($enum);

                return [$key => implode('|', $values)];
            }

            return [$key => 'string'];
        }

        return [$key => 'mixed'];
    }

    private static function describeEnum($resourceModel, $key)
    {
        if (!$resourceModel) {
            return null;
        }
        if (enum_exists($resourceModel)) {
            return $resourceModel;
        } else {
            $reflectionModel = Reflection::reflection($resourceModel);
            $cast = Reflection::call($reflectionModel->newInstanceWithoutConstructor(), 'casts');

            if (!isset($cast[$key])) {
                return null;
            }

            $enum = $cast[$key];

            if (enum_exists($enum)) {
                return $enum;
            }
        }

        return null;
    }

    private static function describeEnumValues(string|\UnitEnum|\BackedEnum $enum)
    {
        $values = array_map(
            fn($case) => $case instanceof \BackedEnum
                ? $case->value
                : $case->name,
            $enum::cases()
        );

        return $values;
    }

    private function getType(string $class): string
    {
        $reflect = Reflection::reflection($class);

        try {
            return Reflection::call($reflect->newInstanceWithoutConstructor(), 'toType', request());
        } catch (\Throwable $e) {
            Logger::warn([
                'Fail to generate type from {resource::class} for json-api-resource ' . $class,
                $e->getMessage()
            ]);
            Logger::notice('Use custom type from resource::class');
        }

        $type = $this->getResourceClass($reflect) ?? $reflect->getName();

        return Str::kebab(Str::beforeLast(Str::afterLast($type, "\\"), 'Resource'));
    }
}
