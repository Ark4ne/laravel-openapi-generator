<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Exceptions;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\ResponseDescription;

/**
 * `exceptions.map` of the configuration (`Exception::class => 402`, or `['status' => 402, 'description' => …]`), the
 * exception or its closest parent class: for the exceptions an application cannot annotate, and those
 * `bootstrap/app.php` renders through closures.
 */
final readonly class ConfiguredExceptionMapper implements ExceptionMapper
{
    public const PRIORITY = 400;

    public function priority(): int
    {
        return self::PRIORITY;
    }

    public function supports(string $exception, GenerationContext $context): bool
    {
        return self::entry($exception, $context) !== null;
    }

    public function map(string $exception, GenerationContext $context): ?ResponseDescription
    {
        $entry = self::entry($exception, $context);

        return $entry === null ? null : ErrorSchemas::response($entry[0], $entry[1], $context);
    }

    /**
     * @return array{int, string|null}|null
     */
    private static function entry(string $exception, GenerationContext $context): ?array
    {
        $found = null;
        $closest = null;

        // The most specific class wins, whatever the order of the configuration.
        foreach ($context->configuration->exceptions->map as $class => $response) {
            if (! is_a($exception, $class, true) || ($closest !== null && ! is_subclass_of($class, $closest))) {
                continue;
            }

            $status = is_int($response) ? $response : ($response['status'] ?? null);
            $description = is_array($response) ? $response['description'] ?? null : null;

            if (is_int($status)) {
                $found = [$status, is_string($description) ? $description : null];
                $closest = $class;
            }
        }

        return $found;
    }
}
