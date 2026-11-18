<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Context;

use Closure;
use Illuminate\Http\Request;

/**
 * The sample request of a generation, prepared once: each caller gets its own copy, so that what an application does
 * to one request never reaches the next.
 */
final class SampleRequest
{
    private ?Request $prepared = null;

    /**
     * A copy of the request `$prepare` gives, called on the first use only.
     *
     * @param Closure(): Request $prepare
     */
    public function get(Closure $prepare): Request
    {
        $this->prepared ??= $prepare();

        return clone $this->prepared;
    }
}
