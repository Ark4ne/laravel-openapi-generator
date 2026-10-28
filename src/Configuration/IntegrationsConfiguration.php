<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Configuration;

use Ark4ne\OpenApi\Exceptions\InvalidConfigurationException;

/**
 * Options of the integrations shipped with the generator (`integrations`).
 */
final readonly class IntegrationsConfiguration
{
    /**
     * @param bool $jsonApiErrors the error responses are JSON:API error documents (`integrations.json_api.errors`)
     */
    public function __construct(
        public bool $jsonApiErrors = false,
    ) {}

    /**
     * @param array<array-key, mixed> $config
     *
     * @throws InvalidConfigurationException
     */
    public static function fromArray(string $key, array $config): self
    {
        ConfigurationValues::keys($key, $config, ['json_api']);
        $jsonApi = ConfigurationValues::array("{$key}.json_api", $config['json_api'] ?? []);
        ConfigurationValues::keys("{$key}.json_api", $jsonApi, ['errors']);

        return new self(ConfigurationValues::boolean("{$key}.json_api.errors", $jsonApi['errors'] ?? false));
    }
}
