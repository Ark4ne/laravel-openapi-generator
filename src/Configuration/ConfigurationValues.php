<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Configuration;

use Ark4ne\OpenApi\Exceptions\InvalidConfigurationException;

/**
 * Checks shared by the `fromArray()` of the configuration objects.
 */
final class ConfigurationValues
{
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
}
