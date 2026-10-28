<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Configuration;

use Ark4ne\OpenApi\Exceptions\InvalidConfigurationException;

/**
 * Which routes a document contains (`documents.<name>.routes`).
 *
 * A route is kept when it matches `include` (every route when `include` is empty), matches neither `exclude` nor
 * `internal`, and is not a redirect, a view or the fallback route.
 */
final readonly class RouteFilterConfiguration
{
    /**
     * Routes of Laravel and of common packages, never documented unless removed from `internal`.
     */
    public const INTERNAL = [
        'up',
        'sanctum/csrf-cookie',
        'broadcasting/auth',
        'storage/*',
        '_ignition/*',
        '_debugbar/*',
        '_boost/*',
        'telescope/*',
        'horizon/*',
        'pulse',
        'livewire/*',
    ];

    /**
     * @param list<string> $ignoredMethods upper case
     * @param list<string> $internal URI masks always left out
     */
    public function __construct(
        public RouteMasks $include = new RouteMasks(uri: ['api/*']),
        public RouteMasks $exclude = new RouteMasks(),
        public array $ignoredMethods = ['HEAD'],
        public array $internal = self::INTERNAL,
        public RouteOrder $order = RouteOrder::Registration,
    ) {}

    /**
     * @param array<array-key, mixed> $config
     *
     * @throws InvalidConfigurationException
     */
    public static function fromArray(string $key, array $config): self
    {
        $keys = ['include', 'exclude', 'ignored_methods', 'internal', 'order'];

        foreach (array_keys($config) as $name) {
            if (! in_array($name, $keys, true)) {
                throw InvalidConfigurationException::forKey($key, 'the keys ' . implode(', ', $keys), $name);
            }
        }

        $defaults = new self();

        return new self(
            array_key_exists('include', $config) ? RouteMasks::fromArray("{$key}.include", $config['include']) : $defaults->include,
            RouteMasks::fromArray("{$key}.exclude", $config['exclude'] ?? null),
            array_map(strtoupper(...), ConfigurationValues::strings("{$key}.ignored_methods", $config['ignored_methods'] ?? $defaults->ignoredMethods)),
            ConfigurationValues::strings("{$key}.internal", $config['internal'] ?? $defaults->internal),
            self::order("{$key}.order", $config['order'] ?? $defaults->order->value),
        );
    }

    private static function order(string $key, mixed $order): RouteOrder
    {
        $cases = array_map(static fn(RouteOrder $case): string => $case->value, RouteOrder::cases());

        if (! is_string($order) || ($case = RouteOrder::tryFrom($order)) === null) {
            throw InvalidConfigurationException::forKey($key, 'one of ' . implode(', ', $cases), $order);
        }

        return $case;
    }
}
