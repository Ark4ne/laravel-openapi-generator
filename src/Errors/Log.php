<?php

namespace Ark4ne\OpenApi\Errors;

use BadMethodCallException;
use Closure;

/**
 * @method static void alert(string $context, string $message)
 * @method static void error(string $context, string $message)
 * @method static void warn(string $context, string $message)
 * @method static void info(string $context, string $message)
 * @method static void line(string $context, string $message)
 */
class Log
{
    private const LEVELS = [
        'alert',
        'error',
        'warn',
        'info',
        'line'
    ];

    /**
     * @var array<Closure(string, string, string):void>
     */
    private static array $interseptors = [];

    /**
     * @param Closure(string, string, string):void $interseptor
     *
     * @return void
     */
    public static function interseptor(Closure $interseptor): void
    {
        self::$interseptors[] = $interseptor;
    }

    public static function log(string $level, string $context, string $message): void
    {
        foreach (self::$interseptors as $interseptor) {
            $interseptor($level, $context, $message);
        }
    }

    /**
     * @param string        $name
     * @param array<string> $arguments
     *
     * @return void
     */
    public static function __callStatic(string $name, array $arguments)
    {
        if (!in_array($name, self::LEVELS, true)) {
            throw new BadMethodCallException("Unknown method $name.");
        }

        self::log($name, $arguments[0], $arguments[1]);
    }
}
