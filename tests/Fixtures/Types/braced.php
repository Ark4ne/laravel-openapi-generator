<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Types\Braced {
    use Ark4ne\OpenApi\Tests\Fixtures\Routing\Models\User;

    final class BracedSignatures
    {
        /**
         * @return User
         */
        public function user(): object
        {
            return new User();
        }
    }
}
