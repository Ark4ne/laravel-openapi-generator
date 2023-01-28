<?php

namespace Ark4ne\OpenApi\Transformers\Middlewares;

use Ark4ne\OpenApi\Contracts\Entry;
use Ark4ne\OpenApi\Contracts\Transformer;
use Ark4ne\OpenApi\Documentation\Request\Security;
use Ark4ne\OpenApi\Documentation\Request\SecurityRequirement;
use Ark4ne\OpenApi\Documentation\RequestEntry;

class ApplyXsrfSecurity implements Transformer
{
    public function transform(Entry $entry, RequestEntry $request, array $responses): void
    {
        $request->addSecurity((new SecurityRequirement)
            ->addSecurity(
                Security::component("X-XSRF-TOKEN", fn(Security $security) => $security
                    ->type(Security::TYPE_API_KEY)
                    ->name('X-XSRF-TOKEN')
                    ->in(Security::IN_HEADER))
            )
            ->addSecurity(
                Security::component("XSRF-TOKEN", fn(Security $security) => $security
                    ->type(Security::TYPE_API_KEY)
                    ->name('XSRF-TOKEN')
                    ->in(Security::IN_COOKIE))
            )
        );
    }
}
