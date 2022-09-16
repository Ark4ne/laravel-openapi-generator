<?php

namespace Ark4ne\OpenApi\Support\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void start(string $operation, string $lvl = null)
 * @method static void end(string $lvl = null, string $message = null)
 * @method static void notice(string $message = null)
 * @method static void info(string $message = null)
 * @method static void success(string $message = null)
 * @method static void warn(string $message = null)
 * @method static void error(string $message = null)
 * @method static void interseptor(\Closure $closure)
 */
class Logger extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Ark4ne\OpenApi\Service\Logger::class;
    }
}
