<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Support;

use DateTimeImmutable;

/**
 * PHP date formats and the OpenAPI format of the strings they give (`date_formats` of the configuration).
 */
final class DateFormats
{
    /**
     * Used when the configuration gives none.
     */
    public const DEFAULTS = [
        'Y-m-d' => 'date',
        'Y-m-d H:i:s' => 'date-time',
        'Y-m-d\TH:i:sP' => 'date-time',
        'Y-m-d\TH:i:s.u\Z' => 'date-time',
    ];

    /**
     * The OpenAPI format of a string written in one of the formats, `null` when it is in none.
     *
     * @param array<string, string> $formats PHP format → OpenAPI format; the defaults when empty
     */
    public static function of(string $value, array $formats = []): ?string
    {
        foreach ($formats === [] ? self::DEFAULTS : $formats as $format => $openapi) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $value);

            if ($date !== false && $date->format($format) === $value) {
                return $openapi;
            }
        }

        return null;
    }
}
