<?php

namespace Ark4ne\OpenApi\Parsers\Responses\Concerns;

use Ark4ne\OpenApi\Support\Fake;
use Ark4ne\OpenApi\Support\Reflection;
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
                    'type' => $this->getType($class),
                    'attributes' => $this->getArrayKeys($instance, 'toAttributes', $request),
                    'relationships' => collect(Reflection::call($instance, 'toRelationships', $request))->map(function (
                        $relationship
                    ) {
                        $sample = [
                            'id' => 'mixed',
                            'type' => $this->getType(Reflection::reflection($relationship->getResource()))
                        ];

                        $data = Reflection::read($relationship, 'asCollection')
                            ? [$sample, $sample]
                            : $sample;

                        $relationshipLinks = Reflection::read($relationship, 'links');
                        try {
                            $links = $relationshipLinks(new Fake);
                        } catch (\Throwable $e) {
                            // todo log
                        }

                        $relationshipMeta = Reflection::read($relationship, 'meta');
                        try {
                            $meta = $relationshipMeta(new Fake);
                        } catch (\Throwable $e) {
                            // todo log
                        }

                        return [
                                'data' => $data,
                            ] + array_filter([
                                'links' => $links ?? null,
                                'meta' => $meta ?? null,
                            ]);
                    })->all()
                ] + array_filter([
                    'meta' => $this->getArrayKeys($instance, 'toResourceMeta', $request),
                    'links' => $this->getArrayKeys($instance, 'toLinks', $request),
                ]),
        ];
    }

    protected function mergeResponseWithStructure(Response $response, array $structure)
    {
        $merge = function($data, $structure){
            $data['attributes'] = array_merge(
                $structure['data']['attributes'],
                array_filter($data['attributes'], static fn($v) => $v !== '' && $v !== [])
            );

            $data['relationships'] = array_merge(
                $structure['data']['relationships'],
                array_filter($data['relationships'])
            );

            $data['relationships'] = collect($data['relationships'])
                ->map(fn($value, $key) => array_merge($structure['data']['relationships'][$key] ?? [], $value))
                ->all();

            return $data;
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
     * @param $instance
     * @param string $method
     * @param $request
     *
     * @throws \ReflectionException
     * @return string[]
     */
    private function getArrayKeys($instance, string $method, $request = null): array
    {
        $request ??= request();

        try {
            return array_fill_keys(
                collect(Reflection::call($instance, $method, $request))->keys()->all(),
                'mixed'
            );
        } catch (\Throwable $e) {
            // TODO log
            return [];
        }
    }

    private function getType(\ReflectionClass $class): string
    {
        $type = $this->getResourceClass($class) ?? $class->getName();

        return Str::kebab(Str::beforeLast(Str::afterLast($type, "\\"), 'Resource'));
    }
}
