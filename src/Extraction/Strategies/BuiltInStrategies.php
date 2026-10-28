<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Strategies;

use Ark4ne\OpenApi\Configuration\StrategyConfiguration;
use Ark4ne\OpenApi\Exceptions\InvalidConfigurationException;

/**
 * Builds the naming strategies of a document from its configuration: a built-in keyword, or a class implementing
 * the strategy interface, built without argument (the service provider builds them through the container, E10).
 */
final class BuiltInStrategies
{
    /**
     * @throws InvalidConfigurationException
     */
    public static function summary(StrategyConfiguration $configuration, string $key = 'strategies.summary'): SummaryStrategy
    {
        return in_array($configuration->by, BuiltInSummaryStrategy::BY, true)
            ? new BuiltInSummaryStrategy($configuration->by, $configuration->pattern())
            : self::custom($configuration, SummaryStrategy::class, BuiltInSummaryStrategy::BY, $key);
    }

    /**
     * @throws InvalidConfigurationException
     */
    public static function tags(StrategyConfiguration $configuration, string $key = 'strategies.tags'): TagStrategy
    {
        return in_array($configuration->by, BuiltInTagStrategy::BY, true)
            ? new BuiltInTagStrategy($configuration->by, $configuration->pattern())
            : self::custom($configuration, TagStrategy::class, BuiltInTagStrategy::BY, $key);
    }

    /**
     * @throws InvalidConfigurationException
     */
    public static function groups(?StrategyConfiguration $configuration, string $key = 'strategies.groups'): ?GroupStrategy
    {
        if ($configuration === null) {
            return null;
        }

        return in_array($configuration->by, BuiltInGroupStrategy::BY, true)
            ? new BuiltInGroupStrategy($configuration->by, $configuration->pattern())
            : self::custom($configuration, GroupStrategy::class, BuiltInGroupStrategy::BY, $key);
    }

    /**
     * @throws InvalidConfigurationException
     */
    public static function operationId(StrategyConfiguration $configuration, string $key = 'strategies.operation_id'): OperationIdStrategy
    {
        return in_array($configuration->by, BuiltInOperationIdStrategy::BY, true)
            ? new BuiltInOperationIdStrategy($configuration->by)
            : self::custom($configuration, OperationIdStrategy::class, BuiltInOperationIdStrategy::BY, $key);
    }

    /**
     * @throws InvalidConfigurationException
     */
    public static function query(StrategyConfiguration $configuration, string $key = 'strategies.query'): QueryStrategy
    {
        return in_array($configuration->by, BuiltInQueryStrategy::BY, true)
            ? new BuiltInQueryStrategy($configuration->by)
            : self::custom($configuration, QueryStrategy::class, BuiltInQueryStrategy::BY, $key);
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
    private static function custom(StrategyConfiguration $configuration, string $interface, array $keywords, string $key): object
    {
        $class = $configuration->by;

        if (! class_exists($class) || ! is_a($class, $interface, true)) {
            throw InvalidConfigurationException::forKey("{$key}.by", 'one of ' . implode(', ', $keywords) . " or a class implementing {$interface}", $class);
        }

        return new $class();
    }
}
