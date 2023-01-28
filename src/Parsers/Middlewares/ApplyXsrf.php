<?php

namespace Ark4ne\OpenApi\Parsers\Middlewares;

use Ark4ne\OpenApi\Contracts\Entry;
use Ark4ne\OpenApi\Documentation\Request\Parameter;
use Ark4ne\OpenApi\Documentation\Request\Security;
use Ark4ne\OpenApi\Documentation\RequestEntry;

class ApplyXsrf
{
    public function parse(Entry $entry, RequestEntry $request): void
    {
        $request
            ->addSecurity((new Security())
                ->type(Security::TYPE_API_KEY)
                ->name('X-XSRF-TOKEN')
                ->in(Security::IN_HEADER))
            ->addSecurity((new Security())
                ->type(Security::TYPE_API_KEY)
                ->name('XSRF-TOKEN')
                ->in(Security::IN_COOKIE))
            ->addHeader((new Parameter('X-XSRF-TOKEN'))
                ->string()
                ->pattern('/(?<token>.+)'));
    }
}
