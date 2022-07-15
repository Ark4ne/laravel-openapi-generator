<?php

namespace Ark4ne\OpenApi\Support;

class Arr extends \Illuminate\Support\Arr
{
    public static function get($array, $key, $default = null): mixed
    {
        if (!self::accessible($array)) {
            return value($default);
        }

        if (is_null($key)) {
            return $array;
        }

        if (static::exists($array, $key)) {
            return $array[$key];
        }

        if (!str_contains($key, '.')) {
            return $array[$key] ?? value($default);
        }

        $sub = $key;
        while (str_contains($sub, '.')) {
            if (static::exists($array, $sub = substr($sub, 0, strrpos($sub, '.')))) {
                if (static::accessible($array[$sub])) {
                    return static::get($array[$sub], str_replace("$sub.", '', $key));
                }

                return $array[$sub];
            }
        }

        return value($default);
    }
}
