<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Laravel;

use Ark4ne\OpenApi\Configuration\ExtensionsConfiguration;
use Ark4ne\OpenApi\Exceptions\InvalidConfigurationException;
use Ark4ne\OpenApi\Extraction\Operations\OperationExtractor;
use Ark4ne\OpenApi\Extraction\Requests\RequestExtractor;
use Ark4ne\OpenApi\Extraction\Responses\ResponseExtractor;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;
use Ark4ne\OpenApi\Generation\GeneratorExtensions;
use Ark4ne\OpenApi\Generation\Pass;
use Ark4ne\OpenApi\Sampling\Sampler;
use Illuminate\Contracts\Container\Container;

/**
 * Builds the extensions of the application through the container: the classes of `extensions`, then the services
 * tagged by third-party packages (`openapi.operation-extractors`, `openapi.request-extractors`,
 * `openapi.rule-mappers`, `openapi.response-extractors`, `openapi.samplers`, `openapi.passes`).
 */
final readonly class ExtensionsResolver
{
    public const TAGS = [
        'operation_extractors' => 'openapi.operation-extractors',
        'request_extractors' => 'openapi.request-extractors',
        'rule_mappers' => 'openapi.rule-mappers',
        'response_extractors' => 'openapi.response-extractors',
        'samplers' => 'openapi.samplers',
        'passes' => 'openapi.passes',
    ];

    public function __construct(
        private Container $container,
    ) {}

    /**
     * @throws InvalidConfigurationException when an extension does not implement the interface of its registry
     */
    public function resolve(ExtensionsConfiguration $configuration): GeneratorExtensions
    {
        return new GeneratorExtensions(
            $this->of('operation_extractors', $configuration->operationExtractors, OperationExtractor::class),
            $this->of('request_extractors', $configuration->requestExtractors, RequestExtractor::class),
            $this->of('rule_mappers', $configuration->ruleMappers, RuleMapper::class),
            $this->of('response_extractors', $configuration->responseExtractors, ResponseExtractor::class),
            $this->of('samplers', $configuration->samplers, Sampler::class),
            $this->of('passes', $configuration->passes, Pass::class),
        );
    }

    /**
     * @template T of object
     *
     * @param list<class-string> $classes
     * @param class-string<T> $interface
     *
     * @return list<T>
     *
     * @throws InvalidConfigurationException
     */
    private function of(string $key, array $classes, string $interface): array
    {
        $extensions = [];

        foreach ($classes as $class) {
            $extension = $this->container->make($class);
            $extensions[] = $extension instanceof $interface ? $extension : throw InvalidConfigurationException::forKey("extensions.{$key}", "classes implementing {$interface}", $class);
        }

        foreach ($this->container->tagged(self::TAGS[$key]) as $extension) {
            $extensions[] = $extension instanceof $interface ? $extension : throw InvalidConfigurationException::forTag(self::TAGS[$key], "services implementing {$interface}", is_object($extension) ? $extension::class : $extension);
        }

        return $extensions;
    }
}
