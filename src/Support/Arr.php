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

    public static function undot($array, string $saveKey = null)
    {
        $results = [];

        foreach ($array as $key => $value) {
            self::apply($results, $key, $value, $saveKey);
        }

        return $results;
    }

    public static function apply(&$array, $key, $value, string $saveKey = null): mixed
    {
        if (is_null($key)) {
            return $array = $value;
        }

        $keys = explode('.', $key);

        foreach ($keys as $i => $key) {
            if (count($keys) === 1) {
                break;
            }

            unset($keys[$i]);

            // If the key doesn't exist at this depth, we will just create an empty array
            // to hold the next value, allowing us to create the arrays to hold final
            // values at the correct depth. Then we'll keep digging into the array.
            if (!isset($array[$key])) {
                $array[$key] = [];
            }
            if (!is_array($array[$key])) {
                $array[$key] = $saveKey
                    ? [$saveKey => $array[$key]]
                    : [];
            }

            $array = &$array[$key];
        }

        $array[array_shift($keys)] = $value;

        return $array;
    }
}
