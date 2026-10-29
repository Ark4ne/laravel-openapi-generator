<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Strategies;

use Ark4ne\OpenApi\Configuration\StrategyConfiguration;
use Ark4ne\OpenApi\Exceptions\InvalidConfigurationException;
use Illuminate\Contracts\Container\Container;

/**
 * Builds the naming strategies of a document from its configuration: a built-in keyword, or a class implementing
 * the strategy interface, built through the container when there is one (its dependencies injected), without argument
 * otherwise.
 */
final class BuiltInStrategies
{
    /**
     * @throws InvalidConfigurationException
     */
    public static function summary(StrategyConfiguration $configuration, string $key = 'strategies.summary', ?Container $container = null): SummaryStrategy
    {
        return in_array($configuration->by, BuiltInSummaryStrategy::BY, true)
            ? new BuiltInSummaryStrategy($configuration->by, $configuration->pattern())
            : self::custom($configuration, SummaryStrategy::class, BuiltInSummaryStrategy::BY, $key, $container);
    }

    /**
     * @throws InvalidConfigurationException
     */
    public static function tags(StrategyConfiguration $configuration, string $key = 'strategies.tags', ?Container $container = null): TagStrategy
    {
        return in_array($configuration->by, BuiltInTagStrategy::BY, true)
            ? new BuiltInTagStrategy($configuration->by, $configuration->pattern())
            : self::custom($configuration, TagStrategy::class, BuiltInTagStrategy::BY, $key, $container);
    }

    /**
     * @throws InvalidConfigurationException
     */
    public static function groups(?StrategyConfiguration $configuration, string $key = 'strategies.groups', ?Container $container = null): ?GroupStrategy
    {
        if ($configuration === null) {
            return null;
        }

        return in_array($configuration->by, BuiltInGroupStrategy::BY, true)
            ? new BuiltInGroupStrategy($configuration->by, $configuration->pattern())
            : self::custom($configuration, GroupStrategy::class, BuiltInGroupStrategy::BY, $key, $container);
    }

    /**
     * @throws InvalidConfigurationException
     */
    public static function operationId(StrategyConfiguration $configuration, string $key = 'strategies.operation_id', ?Container $container = null): OperationIdStrategy
    {
        return in_array($configuration->by, BuiltInOperationIdStrategy::BY, true)
            ? new BuiltInOperationIdStrategy($configuration->by)
            : self::custom($configuration, OperationIdStrategy::class, BuiltInOperationIdStrategy::BY, $key, $container);
    }

    /**
     * @throws InvalidConfigurationException
     */
    public static function query(StrategyConfiguration $configuration, string $key = 'strategies.query', ?Container $container = null): QueryStrategy
    {
        return in_array($configuration->by, BuiltInQueryStrategy::BY, true)
            ? new BuiltInQueryStrategy($configuration->by)
            : self::custom($configuration, QueryStrategy::class, BuiltInQueryStrategy::BY, $key, $container);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $interface
     * @param list<string> $keywords
     *
     * @return T
     *
     * @throws InvalidConfigurationException
     */
    private static function custom(StrategyConfiguration $configuration, string $interface, array $keywords, string $key, ?Container $container): object
    {
        $class = $configuration->by;

        if (! class_exists($class) || ! is_a($class, $interface, true)) {
            throw InvalidConfigurationException::forKey("{$key}.by", 'one of ' . implode(', ', $keywords) . " or a class implementing {$interface}", $class);
        }

        $strategy = $container === null ? new $class() : $container->make($class);

        return $strategy instanceof $interface ? $strategy : throw InvalidConfigurationException::forKey("{$key}.by", "a class implementing {$interface}", $class);
    }
}
