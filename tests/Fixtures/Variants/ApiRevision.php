<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Variants;

/**
 * Revisions of an API, oldest first: a backed enum, its variants named by the values.
 */
enum ApiRevision: string
{
    case Initial = '2026-09-01';
    case Labels = '2026-12-01';
}
