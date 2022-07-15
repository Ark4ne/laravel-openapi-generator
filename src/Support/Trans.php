<?php

namespace Ark4ne\OpenApi\Support;

use Closure;

class Trans
{
    /**
     * @param string|string[]      $keys
     * @param string[]             $replace
     * @param null|string|Closure $default
     *
     * @return string|null
     */
    public static function get(array|string $keys, array $replace = [], null|string|Closure $default = null): ?string
    {
        /** @var \Illuminate\Translation\Translator $trans */
        $trans = trans();

        foreach ((array)$keys as $key) {
            foreach ([str_replace('.', '-', $key), $key] as $item) {
                if ($trans->has($item)) {
                    return $trans->get($item, $replace);
                }
            }
        }

        return value($default);
    }

    public static function lang(string $lang): void
    {
        trans()->setLocale($lang);
    }
}
