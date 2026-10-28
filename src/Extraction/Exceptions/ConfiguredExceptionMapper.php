<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Exceptions;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\ResponseDescription;

/**
 * `exceptions.map` of the configuration (`Exception::class => 402`, or `['status' => 402, 'description' => …]`), the
 * exception or a parent class: for the exceptions an application cannot annotate, and those `bootstrap/app.php`
 * renders through closures.
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
        foreach ($context->configuration->exceptions->map as $class => $response) {
            if (! is_a($exception, $class, true)) {
                continue;
            }

            if (is_int($response)) {
                return [$response, null];
            }

            $status = $response['status'] ?? null;
            $description = $response['description'] ?? null;

            if (is_int($status)) {
                return [$status, is_string($description) ? $description : null];
            }
        }

        return null;
    }
}
