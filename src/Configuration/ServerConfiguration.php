<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Configuration;

use Ark4ne\OpenApi\Exceptions\InvalidConfigurationException;

final readonly class ServerConfiguration
{
    /**
     * @param array<string, array{default: string, enum?: list<string>, description?: string}> $variables
     * @param string|null $name written from 3.2 only
     */
    public function __construct(
        public string $url,
        public ?string $description = null,
        public array $variables = [],
        public ?string $name = null,
    ) {}

    /**
     * @param array<array-key, mixed> $config
     *
     * @throws InvalidConfigurationException
     */
    public static function fromArray(string $key, array $config): self
    {
        $variables = [];

        foreach (ConfigurationValues::array("{$key}.variables", $config['variables'] ?? []) as $name => $variable) {
            $variable = ConfigurationValues::array("{$key}.variables.{$name}", $variable);
            $entry = ['default' => ConfigurationValues::string("{$key}.variables.{$name}.default", $variable['default'] ?? null)];

            if (isset($variable['enum'])) {
                $entry['enum'] = ConfigurationValues::strings("{$key}.variables.{$name}.enum", $variable['enum']);
            }

            if (isset($variable['description'])) {
                $entry['description'] = ConfigurationValues::string("{$key}.variables.{$name}.description", $variable['description']);
            }

            $variables[(string) $name] = $entry;
        }

        return new self(
            ConfigurationValues::string("{$key}.url", $config['url'] ?? null),
            ConfigurationValues::nullableString("{$key}.description", $config['description'] ?? null),
            $variables,
            ConfigurationValues::nullableString("{$key}.name", $config['name'] ?? null),
        );
    }
}
