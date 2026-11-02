<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Types;

use Ark4ne\OpenApi\Attributes\Response;

/**
 * An action without return type, whose responses are declared on its controller.
 */
#[Response(401, description: 'Unauthenticated')]
final class UntypedResponseController
{
    public function nothing() {}
}
