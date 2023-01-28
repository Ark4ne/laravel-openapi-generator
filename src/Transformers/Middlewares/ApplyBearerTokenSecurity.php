<?php

namespace Ark4ne\OpenApi\Transformers\Middlewares;

use Ark4ne\OpenApi\Contracts\Entry;
use Ark4ne\OpenApi\Contracts\Transformer;
use Ark4ne\OpenApi\Documentation\Request\Security;
use Ark4ne\OpenApi\Documentation\Request\SecurityRequirement;
use Ark4ne\OpenApi\Documentation\RequestEntry;

class ApplyBearerTokenSecurity implements Transformer
{
    public function transform(Entry $entry, RequestEntry $request, array $responses): void
    {
        $request
            ->addSecurity((new SecurityRequirement)->addSecurity(
                Security::component("Authorization-Bearer", static fn(Security $security) => $security
                    ->type(Security::TYPE_HTTP)
                    ->scheme('bearer')
                    ->bearerFormat('token'))
            ));
    }
}
