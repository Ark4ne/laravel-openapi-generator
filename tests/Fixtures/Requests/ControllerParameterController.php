<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Requests;

use Ark4ne\OpenApi\Attributes\Cookie;
use Ark4ne\OpenApi\Attributes\Header;
use Ark4ne\OpenApi\Attributes\QueryParameter;

/**
 * Parameters declared on the controller, shared by its actions; the request and the action complete them.
 */
#[QueryParameter('page', type: 'positive-int')]
#[Header('X-Tenant', type: 'int', required: true)]
#[Header('X-Api-Version', type: 'string', description: 'From the controller')]
#[Cookie('session', description: 'From the controller')]
final class ControllerParameterController
{
    #[Cookie('session', required: true)]
    public function shared(AttributedRequest $request): void {}
}
