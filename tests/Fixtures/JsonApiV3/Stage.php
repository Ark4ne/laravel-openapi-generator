<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3;

enum Stage: string
{
    case Draft = 'draft';
    case Published = 'published';
}
