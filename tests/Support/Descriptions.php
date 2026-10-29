<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Support;

use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Ark4ne\OpenApiWriter\Enums\HttpMethod;

/**
 * Object Mother of the descriptions the passes receive.
 */
final class Descriptions
{
    /**
     * `GET /api/users`, answering 200, with the given middlewares.
     */
    public static function operation(HttpMethod|string $method = HttpMethod::Get, string $path = '/api/users', string ...$middlewares): OperationDescription
    {
        $label = ($method instanceof HttpMethod ? strtoupper($method->value) : $method) . " {$path}";

        return OperationDescription::create($method, $path)
            ->route($label)
            ->middlewares(...$middlewares)
            ->responses(ResponseDescription::ok()->description('OK'));
    }

    public static function document(OperationDescription ...$operations): DocumentDescription
    {
        return DocumentDescription::create()->operations(...$operations);
    }
}
