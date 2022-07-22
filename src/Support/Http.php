<?php

namespace Ark4ne\OpenApi\Support;

class Http
{
    public static function acceptBody(string $method): bool
    {
        $method = strtoupper($method);

        return !($method === 'GET' || $method === 'HEAD' || $method === 'DELETE');
    }

    public static function canReturnContent(string $method): bool
    {
        $method = strtoupper($method);

        return $method !== 'HEAD';
    }
}
