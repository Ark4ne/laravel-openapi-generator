<?php

namespace Ark4ne\OpenApi\Support\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void request(string $method, string $uri)
 * @method static void start(string $operation, string $lvl = null)
 * @method static void end(string $lvl = null, string $message = null)
 * @method static void notice(mixed $message = null)
 * @method static void info(mixed $message = null)
 * @method static void success(mixed $message = null)
 * @method static void warn(mixed $message = null)
 * @method static void error(mixed $message = null)
 * @method static void interseptor(\Closure $closure)
 */
class Logger extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Ark4ne\OpenApi\Service\Logger::class;
    }
}
