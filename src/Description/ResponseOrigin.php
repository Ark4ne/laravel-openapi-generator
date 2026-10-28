<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Description;

/**
 * Where a response comes from: a declared response replaces an inferred one of the same status.
 */
enum ResponseOrigin: string
{
    /** Inferred from the return type and the sampled resource. */
    case Inferred = 'inferred';

    /** Declared by `#[Response]`. */
    case Declared = 'declared';

    /** Added by a pass (error responses). */
    case Pass = 'pass';
}
