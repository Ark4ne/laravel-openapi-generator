<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Support;

/**
 * Regular expressions as schemas see them: a PCRE written with its delimiters (`/^[a-z]+$/`, as Laravel's `regex`
 * rule and the shapes of `ark4ne/laravel-api-revisions` take it) as the ECMA `pattern` of a schema.
 */
final class Patterns
{
    /**
     * The body between the delimiters of an expression without flags; `null` for an expression with flags or without
     * closing delimiter, which no `pattern` expresses.
     */
    public static function ecma(string $pcre): ?string
    {
        if ($pcre === '') {
            return null;
        }

        $delimiter = $pcre[0];
        $end = strrpos($pcre, $delimiter);

        return $end === false || $end === 0 || substr($pcre, $end + 1) !== '' ? null : substr($pcre, 1, $end - 1);
    }
}
