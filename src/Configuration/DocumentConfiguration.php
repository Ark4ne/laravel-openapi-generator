<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Configuration;

use Ark4ne\OpenApi\Exceptions\InvalidConfigurationException;
use Ark4ne\OpenApiWriter\Enums\Strictness;
use Ark4ne\OpenApiWriter\Enums\Version;

/**
 * One document to generate (`documents.<name>` of the configuration).
 */
final readonly class DocumentConfiguration
{
    /**
     * @param list<ServerConfiguration> $servers
     * @param list<class-string> $webhooks classes carrying `#[Webhook]`, described without route
     * @param array<string, mixed> $extensions `x-*` extensions of the document
     */
    public function __construct(
        public string $name,
        public InfoConfiguration $info,
        public Version $version = Version::V3_1_2,
        public Strictness $strictness = Strictness::Strict,
        public array $servers = [],
        public RouteFilterConfiguration $routes = new RouteFilterConfiguration(),
        public OutputConfiguration $output = new OutputConfiguration(),
        public StrategyConfiguration $summary = new StrategyConfiguration('doc'),
        public StrategyConfiguration $tags = new StrategyConfiguration('controller'),
        public ?StrategyConfiguration $groups = null,
        public StrategyConfiguration $operationId = new StrategyConfiguration('name'),
        public StrategyConfiguration $query = new StrategyConfiguration('all'),
        public bool $useReferences = true,
        public array $webhooks = [],
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
        return new self($name, new InfoConfiguration('', ''));
    }
}
