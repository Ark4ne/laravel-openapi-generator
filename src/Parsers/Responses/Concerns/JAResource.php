<?php

namespace Ark4ne\OpenApi\Parsers\Responses\Concerns;

use Ark4ne\JsonApi\Descriptors\Describer;
use Ark4ne\JsonApi\Descriptors\Relations\Relation;
use Ark4ne\JsonApi\Descriptors\Relations\RelationMany;
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

    protected function generateStructure($instance, \ReflectionClass $class)
    {
        $request = request();

        return [
            'data' => [
                    'id' => 'int|string',
                    'type' => $this->getType($instance::class),
                    'attributes' => $this->getSamples($instance, 'toAttributes', $request),
                    'relationships' => collect($this->mergeValues(Reflection::call($instance, 'toRelationships', $request)))
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

    protected function mapRelationships($instance, $relationship, $name)
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
            $data['attributes'] = array_merge(
                $structure['data']['attributes'],
                array_filter($data['attributes'], static fn($v) => $v !== '' && $v !== [])
            );

            $data['relationships'] = array_merge(
                $structure['data']['relationships'] ?? [],
                array_filter($data['relationships'] ?? [])
            );

            $data['relationships'] = collect($data['relationships'])
                ->map(fn($value, $key) => array_merge($structure['data']['relationships'][$key] ?? [], $value))
                ->all();

            return array_filter($data);
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
            }
            // Available for json-api v1.2
            elseif (trait_exists(PrepareData::class)) {
                $stub = new class {
                    use PrepareData;
                };

                $method = Reflection::method($stub, 'mergeValues');
                $method->setAccessible(true);

                $mergeValues = static fn($values) => $method->invoke($stub, $values);
            }
            // no merge values
            else {
                $mergeValues = static fn($values) => $values;
            }
        }

        return $mergeValues($values);
    }

    /**
     * @param                                                          $instance
     * @param string                                                   $method
     * @param callable(string $key, mixed $value):array<string, mixed> $map
     * @param                                                          $request
     *
     * @return array<string, mixed>
     */
    private function mapSamples($instance, string $method, callable $map, $request = null): array
    {
        $request ??= request();

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
        return $this->mapSamples($instance, $method, function ($value, $key) {
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
                return [$key => 'date'];
            }

            return [$key => 'mixed'];
        }, $request);
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
