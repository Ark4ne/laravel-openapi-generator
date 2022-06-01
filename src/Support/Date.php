<?php

namespace Ark4ne\OpenApi\Support;

use DateTime;

class Date
{
    public static function isFormat(string $format): bool
    {
        return DateTime::createFromFormat($format, date($format, 0)) !== false;
    }

    public static function isValidForFormat(string $date, string $format): bool
    {
        if (!self::isFormat($format)) {
            return false;
        }
        $dateObj = DateTime::createFromFormat($format, $date);
        if ($dateObj === false) {
            return false;
        }
        return $dateObj->format($format) === $date;
    }
}
