<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Configuration;

use Ark4ne\OpenApi\Exceptions\InvalidConfigurationException;

/**
 * Where the payload webhooks of a document are found (`webhooks`, overridden by `documents.<name>.webhooks`): the
 * `JsonResource` classes marked `#[Webhook]` declared in the PHP files under `paths`, and the `classes` listed; then the
 * webhooks of the document, payload and route webhooks alike, chosen by their name with `include` and `exclude`.
 *
 * The package configuration scans `app_path()`, merged into the application's even when it is not published; built
 * from an array without the key, nothing is scanned.
 */
final readonly class WebhooksConfiguration
{
    public const KEYS = ['paths', 'classes', 'include', 'exclude'];

    /**
     * @param list<string> $paths directories scanned recursively, or PHP files
     * @param list<class-string> $classes classes added as given
     * @param list<string> $include shell masks on the names of the webhooks kept (`order.*`); `[]`: every webhook
     * @param list<string> $exclude shell masks on the names of the webhooks left out, over `include`
     */
    public function __construct(
        public array $paths = [],
        public array $classes = [],
        public array $include = [],
        public array $exclude = [],
    ) {}

    /**
     * @param array<array-key, mixed> $config the global `webhooks` merged with the document's own
     *
     * @throws InvalidConfigurationException
     */
    public static function fromArray(string $key, array $config): self
    {
        ConfigurationValues::keys($key, $config, self::KEYS);

        return new self(
            ConfigurationValues::strings("{$key}.paths", $config['paths'] ?? []),
            ConfigurationValues::classes("{$key}.classes", $config['classes'] ?? []),
            ConfigurationValues::strings("{$key}.include", $config['include'] ?? []),
            ConfigurationValues::strings("{$key}.exclude", $config['exclude'] ?? []),
        );
    }
}
