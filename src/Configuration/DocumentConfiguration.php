<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Configuration;

use Ark4ne\OpenApi\Exceptions\InvalidConfigurationException;
use Ark4ne\OpenApiWriter\Enums\Strictness;
use Ark4ne\OpenApiWriter\Enums\VendorExtensions;
use Ark4ne\OpenApiWriter\Enums\Version;

/**
 * One document to generate (`documents.<name>` of the configuration).
 *
 * Read so far: `routes` and `strategies`; the other keys keep their defaults until the loading of the configuration
 * is completed (E10).
 */
final readonly class DocumentConfiguration
{
    /**
     * @param list<ServerConfiguration> $servers
     * @param VendorExtensions $vendorExtensions `redoc` writes `x-webhooks` before 3.1 and `x-tagGroups` before 3.2
     * @param array<string, array<string, mixed>> $securitySchemes scheme name → Security Scheme Object, with the
     *        exact OpenAPI field names, in camelCase (`type`, `scheme`, `bearerFormat`, `flows`, `openIdConnectUrl`,
     *        `oauth2MetadataUrl`, `deprecated`…), completing or replacing the schemes inferred from the middlewares
     * @param array{url: string, description?: string}|null $externalDocs
     * @param string|null $self public URI of the generated document (`$self`, 3.2)
     * @param array<string, mixed> $extensions `x-*` extensions of the document
     */
    public function __construct(
        public string $name,
        public InfoConfiguration $info,
        public Version $version = Version::V3_1_2,
        public Strictness $strictness = Strictness::Strict,
        public VendorExtensions $vendorExtensions = VendorExtensions::None,
        public array $servers = [],
        public RouteFilterConfiguration $routes = new RouteFilterConfiguration(),
        public OutputConfiguration $output = new OutputConfiguration(),
        public StrategyConfiguration $summary = new StrategyConfiguration('doc'),
        public StrategyConfiguration $tags = new StrategyConfiguration('controller'),
        public ?StrategyConfiguration $groups = null,
        public StrategyConfiguration $operationId = new StrategyConfiguration('name'),
        public StrategyConfiguration $query = new StrategyConfiguration('all'),
        public bool $useReferences = true,
        public array $securitySchemes = [],
        public ?array $externalDocs = null,
        public ?string $self = null,
        public array $extensions = [],
    ) {}

    /**
     * @param array<array-key, mixed> $config the document entry
     * @param array<array-key, mixed> $output the global `output` entry, overridden by the document's
     * @param list<ServerConfiguration> $servers the global servers, used when the document has none
     *
     * @throws InvalidConfigurationException
     */
    public static function fromArray(string $name, array $config, array $output = [], array $servers = []): self
    {
        $key = "documents.{$name}";
        $routes = $config['routes'] ?? [];
        $strategies = $config['strategies'] ?? [];

        if (! is_array($routes)) {
            throw InvalidConfigurationException::forKey("{$key}.routes", 'an array', $routes);
        }

        if (! is_array($strategies)) {
            throw InvalidConfigurationException::forKey("{$key}.strategies", 'an array', $strategies);
        }

        $groups = $strategies['groups'] ?? null;

        return new self(
            $name,
            new InfoConfiguration('', ''),
            routes: RouteFilterConfiguration::fromArray("{$key}.routes", $routes),
            summary: self::strategy("{$key}.strategies.summary", $strategies['summary'] ?? [], 'doc'),
            tags: self::strategy("{$key}.strategies.tags", $strategies['tags'] ?? [], 'controller'),
            groups: $groups === null ? null : self::strategy("{$key}.strategies.groups", $groups, 'controller'),
            operationId: self::strategy("{$key}.strategies.operation_id", $strategies['operation_id'] ?? [], 'name'),
            query: self::strategy("{$key}.strategies.query", $strategies['query'] ?? [], 'all'),
        );
    }

    /**
     * @throws InvalidConfigurationException
     */
    private static function strategy(string $key, mixed $config, string $default): StrategyConfiguration
    {
        if (! is_array($config)) {
            throw InvalidConfigurationException::forKey($key, 'an array with a `by` key', $config);
        }

        return StrategyConfiguration::fromArray($key, $config, $default);
    }
}
