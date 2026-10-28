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
 * Every key of `documents.<name>` is read; an unknown key is ignored.
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
     * @param list<string> $security names of the security schemes required by default on every operation
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
        public array $security = [],
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
        $documentServers = ConfigurationValues::array("{$key}.servers", $config['servers'] ?? []);
        $externalDocs = $config['external_docs'] ?? null;

        if ($externalDocs !== null && ! (is_array($externalDocs) && is_string($externalDocs['url'] ?? null))) {
            throw InvalidConfigurationException::forKey("{$key}.external_docs", 'null or an array with an url', $externalDocs);
        }

        /** @var array{url: string, description?: string}|null $externalDocs */
        $extensions = ConfigurationValues::array("{$key}.extensions", $config['extensions'] ?? []);

        return new self(
            $name,
            InfoConfiguration::fromArray("{$key}.info", ConfigurationValues::array("{$key}.info", $config['info'] ?? ['title' => $name])),
            ConfigurationValues::enum("{$key}.openapi", $config['openapi'] ?? Version::default()->value, Version::class),
            is_bool($config['strict'] ?? true) ? (($config['strict'] ?? true) ? Strictness::Strict : Strictness::Lenient) : throw InvalidConfigurationException::forKey("{$key}.strict", 'a boolean', $config['strict']),
            ConfigurationValues::enum("{$key}.vendor_extensions", $config['vendor_extensions'] ?? VendorExtensions::None->value, VendorExtensions::class),
            $documentServers === [] ? $servers : array_map(static fn(int|string $index, mixed $server): ServerConfiguration => ServerConfiguration::fromArray("{$key}.servers.{$index}", ConfigurationValues::array("{$key}.servers.{$index}", $server)), array_keys($documentServers), $documentServers),
            output: OutputConfiguration::fromArray("{$key}.output", [...$output, ...ConfigurationValues::array("{$key}.output", $config['output'] ?? [])]),
            routes: RouteFilterConfiguration::fromArray("{$key}.routes", $routes),
            summary: self::strategy("{$key}.strategies.summary", $strategies['summary'] ?? [], 'doc'),
            tags: self::strategy("{$key}.strategies.tags", $strategies['tags'] ?? [], 'controller'),
            groups: $groups === null ? null : self::strategy("{$key}.strategies.groups", $groups, 'controller'),
            operationId: self::strategy("{$key}.strategies.operation_id", $strategies['operation_id'] ?? [], 'name'),
            query: self::strategy("{$key}.strategies.query", $strategies['query'] ?? [], 'all'),
            useReferences: is_bool($config['use_references'] ?? null) ? $config['use_references'] : true,
            securitySchemes: self::schemes("{$key}.security_schemes", $config['security_schemes'] ?? []),
            externalDocs: $externalDocs,
            self: ConfigurationValues::nullableString("{$key}.self", $config['self'] ?? null),
            extensions: array_filter($extensions, static fn(int|string $name): bool => is_string($name), ARRAY_FILTER_USE_KEY),
            security: ConfigurationValues::strings("{$key}.security", $config['security'] ?? []),
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     *
     * @throws InvalidConfigurationException
     */
    private static function schemes(string $key, mixed $schemes): array
    {
        if (! is_array($schemes) || ($schemes !== [] && array_is_list($schemes))) {
            throw InvalidConfigurationException::forKey($key, 'a map of scheme name to Security Scheme Object', $schemes);
        }

        foreach ($schemes as $name => $scheme) {
            if (! is_array($scheme) || ! is_string($scheme['type'] ?? null)) {
                throw InvalidConfigurationException::forKey("{$key}.{$name}", 'a Security Scheme Object with a type', $scheme);
            }
        }

        /** @var array<string, array<string, mixed>> $schemes */
        return $schemes;
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
