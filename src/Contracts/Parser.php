<?php

namespace Ark4ne\OpenApi\Contracts;

use Illuminate\Routing\Route;

interface Parser
{
    public function parse(mixed $element, Entry $entry): mixed;
}
