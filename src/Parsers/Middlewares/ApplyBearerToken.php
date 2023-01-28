<?php

namespace Ark4ne\OpenApi\Parsers\Middlewares;

use Ark4ne\OpenApi\Contracts\Entry;
use Ark4ne\OpenApi\Documentation\Request\Parameter;
use Ark4ne\OpenApi\Documentation\Request\Security;
use Ark4ne\OpenApi\Documentation\RequestEntry;

class ApplyBearerToken
{
    public function parse(Entry $entry, RequestEntry $request): void
    {
        $request
            ->addSecurity((new Security())
                ->type(Security::TYPE_HTTP)
                ->scheme('bearer')
                ->bearerFormat('token'))
            ->addHeader((new Parameter('Authorization'))
                ->string()
                ->pattern('/Bearer (?<token>.+)/'));
    }
}
