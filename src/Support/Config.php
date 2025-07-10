<?php

namespace Ark4ne\OpenApi\Support;

use Illuminate\Support\Str;

/**
 * @method static string title()
 * @method static string description()
 * @method static bool useRef()
 * @method static string[] routes()
 * @method static array nameBy()
 * @method static array tagBy()
 * @method static array groupBy()
 * @method static string[] ignoreVerbs()
 * @method static string outputFile()
 * @method static string outputDir()
 * @method static string parameters(string ...$key)
 * @method static array parsers(?string $type = null)
 * @method static array format(?string $type = null)
 * @method static array connections(?string $value = null)
 * @method static array middlewares(?string $path = null)
 * @method static array versions()
 * @method static string[] languages()
 * @method static array|null servers()
 */
class Config
{
    private static string $version;

    private static function read(string ...$key): mixed
    {
        return config(implode('.', ['openapi', ...$key]));
    }

    public static function version(string $version): void
    {
        self::$version = $version;
    }

    /**
     * @param string       $name
     * @param array<string> $args
     *
     * @return mixed
     */
    public static function __callStatic(string $name, array $args): mixed
    {
        $name = Str::snake($name, '-');

        $read[] = [$name, ...$args];

        if (isset(self::$version)) {
            array_unshift($read, ['versions', self::$version, $name, ...$args]);
        }

        foreach ($read as $entry) {
            $value = self::read(...$entry);

            if (null !== $value) {
                return $value;
            }
        }

        return null;
    }

    public static function flatMode(null|string $is = null): string|bool
    {
        $mode = self::parameters('query', 'flat') ?? 'all';

        return $is === null ? $mode : $mode === $is;
    }
}
