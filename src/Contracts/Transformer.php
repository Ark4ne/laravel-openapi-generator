<?php

namespace Ark4ne\OpenApi\Contracts;

use Ark4ne\OpenApi\Documentation\RequestEntry;
use Ark4ne\OpenApi\Documentation\ResponseEntry;

interface Transformer
{
    /**
     * @param Entry $entry
     * @param RequestEntry $request
     * @param ResponseEntry[] $responses
     * @return void
     */
    public function transform(Entry $entry, RequestEntry $request, array $responses): void;
}
