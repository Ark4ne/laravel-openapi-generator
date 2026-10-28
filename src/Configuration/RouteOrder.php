<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Configuration;

/**
 * Order of the operations in a document (`routes.order`).
 */
enum RouteOrder: string
{
    /** The order in which the application registered its routes. */
    case Registration = 'registration';

    /** Sorted by OpenAPI path; the methods of a path keep their registration order. */
    case Path = 'path';
}
