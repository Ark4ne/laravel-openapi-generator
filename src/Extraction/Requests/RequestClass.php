<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Requests;

use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApi\Types\NamedType;
use Illuminate\Http\Request;

/**
 * The request class an action receives.
 */
final class RequestClass
{
    /**
     * @template T of object
     *
     * @param class-string<T> $parent class or interface the request class must extend or implement
     *
     * @return class-string<Request&T>|null
     */
    public static function of(OperationContext $operation, string $parent = Request::class): ?string
    {
        $type = $operation->requestType;

        if (! $type instanceof NamedType || ! class_exists($type->name) || ! is_a($type->name, Request::class, true) || ! is_a($type->name, $parent, true)) {
            return null;
        }

        /** @var class-string<Request&T> */
        return $type->name;
    }
}
