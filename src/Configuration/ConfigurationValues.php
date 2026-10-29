<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Configuration;

use Ark4ne\OpenApi\Exceptions\InvalidConfigurationException;
use BackedEnum;

/**
 * Checks shared by the `fromArray()` of the configuration objects.
 */
final class ConfigurationValues
{
    /**
     * @throws InvalidConfigurationException
     */
    public static function string(string $key, mixed $value): string
    {
        return is_string($value) ? $value : throw InvalidConfigurationException::forKey($key, 'a string', $value);
    }

    /**
     * @throws InvalidConfigurationException
     */
    public static function nullableString(string $key, mixed $value): ?string
    {
        return $value === null ? null : self::string($key, $value);
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws InvalidConfigurationException
     */
    public static function array(string $key, mixed $value): array
    {
        return is_array($value) ? $value : throw InvalidConfigurationException::forKey($key, 'an array', $value);
    }

    /**
     * @template T of \BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T
     *
     * @throws InvalidConfigurationException
     */
    public static function enum(string $key, mixed $value, string $enum): BackedEnum
    {
        $case = is_string($value) ? $enum::tryFrom($value) : null;

        return $case ?? throw InvalidConfigurationException::forKey($key, 'one of ' . implode(', ', array_map(static fn(BackedEnum $case): string => (string) $case->value, $enum::cases())), $value);
    }

    /**
     * @return list<string>
     *
     * @throws InvalidConfigurationException
     */
    public static function strings(string $key, mixed $values): array
    {
        if (! is_array($values) || ! array_is_list($values) || array_filter($values, is_string(...)) !== $values) {
            throw InvalidConfigurationException::forKey($key, 'a list of strings', $values);
        }

        /** @var list<string> $values */
        return $values;
    }

    /**
     * @throws InvalidConfigurationException
     */
    public static function boolean(string $key, mixed $value): bool
    {
        return is_bool($value) ? $value : throw InvalidConfigurationException::forKey($key, 'a boolean', $value);
    }

    /**
     * @return list<class-string>
     *
     * @throws InvalidConfigurationException
     */
    public static function classes(string $key, mixed $values): array
    {
        if (! is_array($values) || ! array_is_list($values)) {
            throw InvalidConfigurationException::forKey($key, 'a list of classes', $values);
        }

        $classes = [];

        foreach ($values as $value) {
            // An interface bound in the container is resolved as well as a class.
            $classes[] = is_string($value) && (class_exists($value) || interface_exists($value)) ? $value : throw InvalidConfigurationException::forKey($key, 'existing classes or interfaces', $value);
        }

        return $classes;
    }

    /**
     * @param array<array-key, mixed> $config
     * @param list<string> $keys the keys the entry accepts
     *
     * @throws InvalidConfigurationException on the first other key
     */
    public static function keys(string $key, array $config, array $keys): void
    {
        foreach (array_keys($config) as $name) {
            if (! in_array($name, $keys, true)) {
                throw InvalidConfigurationException::forKey($key, 'the keys ' . implode(', ', $keys), $name);
            }
        }
    }
}
