<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Responses\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * An HTTP exception that fixes its status in its constructor.
 */
final class TeapotException extends HttpException
{
    public function __construct()
    {
        parent::__construct(418, "I'm a teapot");
    }
}
