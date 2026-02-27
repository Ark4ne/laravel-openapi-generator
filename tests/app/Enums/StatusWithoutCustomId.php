<?php

namespace Test\app\Enums;

enum StatusWithoutCustomId: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
