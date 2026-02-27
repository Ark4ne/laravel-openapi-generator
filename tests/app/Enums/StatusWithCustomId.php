<?php

namespace Test\app\Enums;

use Ark4ne\OpenApi\Attributes\Id;

#[Id('custom-status')]
enum StatusWithCustomId: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
