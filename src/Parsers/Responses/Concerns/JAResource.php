<?php

namespace Ark4ne\OpenApi\Parsers\Responses\Concerns;

use Ark4ne\OpenApi\Support\Facades\Logger;
use Ark4ne\OpenApi\Support\Fake;
use Ark4ne\OpenApi\Support\Reflection;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

trait JAResource
{
    use Resource;

    protected function generateStructure($instance, \ReflectionClass $class)
    {
        $request = request();

        return [
            'data' => [
                    'id' => 'mixed',
                    'type' => $this->getType($instance::class),
                    'attributes' => $this->getSamples($instance, 'toAttributes', $request),
                    'relationships' => collect(Reflection::call($instance, 'toRelationships', $request))
                        ->map(fn ($relationship, $name) => $this->mapRelationships($instance, $relationship, $name))
                        ->all()
                ] + array_filter([
                    'meta' => $this->getSamples($instance, 'toResourceMeta', $request),
                    'links' => $this->getSamples($instance, 'toLinks', $request),
                ]),
        ];
    }

    protected function mapRelationships($instance, $relationship, $name)
    {
        $resource = $relationship->getResource();

        $sample = [
            'id' => 'mixed',
            'type' => $this->getType($resource)
        ];

        $data = Reflection::read($relationship, 'asCollection')
        || is_subclass_of($resource, ResourceCollection::class)
            ? [$sample, $sample]
            : $sample;

        if ($relationshipLinks = Reflection::read($relationship, 'links')) {
            try {
                $links = $relationshipLinks(new Fake);
            } catch (\Throwable $e) {
                Logger::warn('Fail to generate links for json-api-resource ' . $instance::class);
                Logger::warn($e->getMessage());
                Logger::notice('Use empty array instead');
            }
        }

        if ($relationshipMeta = Reflection::read($relationship, 'meta')) {
            try {
                $meta = $relationshipMeta(new Fake);
            } catch (\Throwable $e) {
                Logger::warn('Fail to generate meta for json-api-resource ' . $instance::class);
                Logger::warn($e->getMessage());
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

    /**
     * @param        $instance
     * @param string $method
     * @param        $request
     *
     * @throws \ReflectionException
     * @return string[]
     */
    private function getSamples($instance, string $method, $request = null): array
    {
        $request ??= request();

        try {
            return array_fill_keys(
                collect(Reflection::call($instance, $method, $request))->keys()->all(),
                'mixed'
            );
        } catch (\Throwable $e) {
            Logger::warn('Fail to generate samples for attributes for json-api-resource ' . $instance::class);
            Logger::warn($e->getMessage());
            Logger::notice('Use empty array instead');
            return [];
        }
    }

    private function getType(string $class): string
    {
        $reflect = Reflection::reflection($class);

        try {
            return Reflection::call($reflect->newInstanceWithoutConstructor(), 'toType', request());
        } catch (\Throwable $e) {
            Logger::warn('Fail to generate type from {resource::class} for json-api-resource ' . $class);
            Logger::warn($e->getMessage());
            Logger::notice('Use custom type from resource::class');
        }

        $type = $this->getResourceClass($reflect) ?? $reflect->getName();

        return Str::kebab(Str::beforeLast(Str::afterLast($type, "\\"), 'Resource'));
    }
}
