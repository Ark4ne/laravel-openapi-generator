<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Routing\Enums;

enum Status: string
{
    case Draft = 'draft';
    case Published = 'published';
}
