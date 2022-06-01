<?php

namespace Ark4ne\OpenApi\Contracts\Documentation;

use Ark4ne\OpenApi\Descriptors\Requests\Describer;

interface DescribableRequest
{
    public function describer(): Describer;

    public function describe(Describer $descriptor): void;
}
