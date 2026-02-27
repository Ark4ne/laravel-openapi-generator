<?php

namespace Test\app\Enums;

use Ark4ne\OpenApi\Attributes\Id;

#[Id('custom-status')]
enum AnotherStatusWithSameId: string
{
    case Yes = 'yes';
    case No = 'no';
}
