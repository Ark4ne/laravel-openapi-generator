<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Types;

/**
 * An action without return type: the generator reports an info.
 */
final class UntypedController
{
    /**
     * Ping the API.
     */
    public function ping() {}
}
