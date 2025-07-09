<?php

namespace Ark4ne\OpenApi\Parsers\Responses\Concerns;

use Ark4ne\JsonApi\Descriptors\Describer;
use Ark4ne\JsonApi\Descriptors\Relations\Relation;
use Ark4ne\JsonApi\Descriptors\Relations\RelationMany;
use Ark4ne\JsonApi\Descriptors\Values\Value;
use Ark4ne\JsonApi\Resources\Relationship;
use Ark4ne\OpenApi\Documentation\Request\Component;
use Ark4ne\OpenApi\Documentation\Request\Parameter;
use Ark4ne\OpenApi\Parsers\Common\EnumToRef;
use Ark4ne\OpenApi\Support\ArrayCache;
use Ark4ne\OpenApi\Support\Config;
use Ark4ne\OpenApi\Support\Facades\Logger;
use Ark4ne\OpenApi\Support\Ref;
use Ark4ne\OpenApi\Support\Reflection;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Str;

trait JAResourceRef
{
    use ValueType;
    use Resource;
    use JAResource;

    protected function resourceToRef($resource)
    {
        $class = Reflection::reflection($resource);

        $ref = Ref::resourceRef($class->getName());

        if (Component::has($ref, Component::SCOPE_SCHEMAS)) {
            return Component::get($ref, Component::SCOPE_SCHEMAS)?->ref();
        }

        $instance = $class->newInstanceWithoutConstructor();
        $instance->resource = $this->getModelFromResource($class);

        $type = $this->getType($instance::class);
        $request = request();

        $component = Component::create($ref, Component::SCOPE_SCHEMAS);

        try {
            $properties[] = (new Parameter('id'))->string();
            $properties[] = (new Parameter('type'))->string()->default($type);
            $properties[] = $this->getRefAttributes($instance, $request);
            $properties[] = $this->getRefRelations($instance, $request);
            $properties[] = $this->getRefLinks($instance, $request);
            $properties[] = $this->getRefMeta($instance, $request);

            $param = (new Parameter($ref))
                ->object()
                ->properties(...array_filter($properties))
                ->x('type', 'resource')
                ->x('name', Str::studly($type));

            $component->object($param);

            return $component->ref();
        } catch (\Throwable $e) {
            Component::drop($ref, Component::SCOPE_SCHEMAS);
            Logger::error('Error generating resource ref: ' . $e->getMessage());
            throw $e;
        }
    }

    private function getRefSamples($instance, $request, $method, $name)
    {
        $attributes = array_values($this->mapSamples(
            $instance,
            $method,
            fn ($value, $name) => $this->getRefValue($instance, $value, $name),
            $request
        ));

        if (empty($attributes)) {
            return null;
        }

        return (new Parameter($name))
            ->object()
            ->properties(...$attributes);
    }

    private function getRefValue($instance, $value, $name)
    {
        $param = (new Parameter(Describer::retrieveName($value, $name)));

        if ($this->instanceof($value, Value::class) && $value->isNullable()) {
            $param->nullable();
        }

        return [
            $name => match (true) {
                $this->isBool($value) => $param->bool(),
                $this->isInt($value) => $param->int(),
                $this->isFloat($value) => $param->float(),
                $this->isString($value) => $param->string(),
                $this->isDate($value) => $param->date(),
                $this->isArray($value) => $param->array()->items((new Parameter('entry'))->string()),
                $this->isStruct($value) => $param->object()->properties(
                    ...collect(($value->retriever())($instance, $name))
                    ->mapWithKeys(fn($value, $key) => $this->getRefValue($instance, $value, $key))
                    ->values()
                    ->all()
                ),
                $this->isEnum($value) => when(
                    self::describeEnum($this->getResourceClass(Reflection::reflection($instance)), $name),
                    fn($enum) => Config::useRef()
                        ? $param->ref((new EnumToRef($enum))->toRef())
                        : (new EnumToRef($enum))->applyOnParameter($param),
                    fn() => $param->string()
                ),
                default => $param->string()->example('mixed'),
            }
        ];
    }

    private function getRefAttributes($instance, $request)
    {
        return $this->getRefSamples($instance, $request, 'toAttributes', 'attributes');
    }

    private function getRefLinks($instance, $request)
    {
        return $this->getRefSamples($instance, $request, 'toLinks', 'links');
    }

    private function getRefMeta($instance, $request)
    {
        return $this->getRefSamples($instance, $request, 'toResourceMeta', 'meta');
    }

    private function getRefRelations($instance, $request)
    {
        if (!Reflection::hasMethod($instance, 'toRelationships')) {
            return null;
        }

        $relations = collect(Reflection::call($instance, 'toRelationships', $request))
            ->mapWithKeys(function ($relationship, $name) use ($instance) {
                $name = Describer::retrieveName($relationship, $name);

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

                $param = (new Parameter($name));

                $id = (new Parameter('id'))->string();

                if ($isCollectionClass && ($collects = $this->getResourceFromCollection(
                        $class->newInstanceWithoutConstructor(),
                        Reflection::tryParseGeneric($class, 'extends'))
                    )) {
                    $resource = $collects;
                }

                $ref = ArrayCache::fetch(
                    ['ja-resource-ref', $instance::class, $this->resourceToRef($resource)],
                    fn () => $this->resourceToRef($resource)
                );

                $resourceType = $this->getType($resource);
                $type = (new Parameter('type'))
                    ->string()
                    ->default($resourceType)
                    ->example($resourceType);

                if ($isCollection) {
                    $param
                        ->array()
                        ->items((new Parameter('entry'))
                            ->object()
                            ->properties($id, $type)
                            ->x('ref', $ref)
                        );
                } else {
                    $param
                        ->object()
                        ->properties($id, $type)
                        ->x('ref', $ref);
                }

                return [$name => $param];
            })
            ->all();

        if (empty($relations)) {
            return null;
        }

        return (new Parameter('relationships'))
            ->object()
            ->properties(...$relations);
    }
}
