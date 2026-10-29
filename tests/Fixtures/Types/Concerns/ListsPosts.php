<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Types\Concerns;

use Ark4ne\OpenApi\Tests\Fixtures\Routing\Models\Post;

trait ListsPosts
{
    /**
     * @return list<Post>
     */
    public function posts(): array
    {
        return [];
    }
}
