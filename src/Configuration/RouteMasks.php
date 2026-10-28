<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Configuration;

use Ark4ne\OpenApi\Exceptions\InvalidConfigurationException;

/**
 * Shell masks (`api/*`, `users.*`) on the URI, the name, the domain and the middlewares of a route.
 *
 * A route matches when one mask of any kind matches it.
 */
final readonly class RouteMasks
{
    private const KINDS = ['uri', 'name', 'domain', 'middleware'];

    /**
     * @param list<string> $uri URI without leading slash (`api/*`)
     * @param list<string> $name route name (`api.users.*`)
     * @param list<string> $domain route domain (`{account}.example.com`, `*.example.com`)
     * @param list<string> $middleware declared middleware (`auth:sanctum`, `auth*`) or its class
     */
    public function __construct(
        public array $uri = [],
        public array $name = [],
        public array $domain = [],
        public array $middleware = [],
    ) {}

    /**
     * A list of URI masks (`['api/*']`), or a map with the `uri`, `name`, `domain` and `middleware` keys.
     *
     * @throws InvalidConfigurationException
     */
    public static function fromArray(string $key, mixed $config): self
    {
        if ($config === null) {
            return new self();
        }

        $expected = 'a list of masks or a map of masks by ' . implode(', ', self::KINDS);

        if (! is_array($config)) {
            throw InvalidConfigurationException::forKey($key, $expected, $config);
        }

        if (array_is_list($config)) {
            return new self(uri: self::strings($key, $config));
        }

        foreach (array_keys($config) as $kind) {
            if (! in_array($kind, self::KINDS, true)) {
                throw InvalidConfigurationException::forKey($key, $expected, $kind);
            }
        }

        return new self(
            self::masks("{$key}.uri", $config['uri'] ?? []),
            self::masks("{$key}.name", $config['name'] ?? []),
            self::masks("{$key}.domain", $config['domain'] ?? []),
            self::masks("{$key}.middleware", $config['middleware'] ?? []),
        );
    }

    public function isEmpty(): bool
    {
        return $this->uri === [] && $this->name === [] && $this->domain === [] && $this->middleware === [];
    }

    /**
     * A mask, or a list of masks.
     *
     * @return list<string>
     */
    private static function masks(string $key, mixed $masks): array
    {
        return self::strings($key, is_string($masks) ? [$masks] : $masks);
    }

    /**
     * @return list<string>
     */
    private static function strings(string $key, mixed $values): array
    {
        if (! is_array($values) || ! array_is_list($values) || array_filter($values, is_string(...)) !== $values) {
            throw InvalidConfigurationException::forKey($key, 'a list of strings', $values);
        }

        /** @var list<string> $values */
        return $values;
    }
}
