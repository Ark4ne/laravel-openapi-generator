<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Responses\Exceptions;

use Ark4ne\OpenApi\Attributes\Response;
use RuntimeException;

#[Response(409, description: 'Seat already taken')]
class SeatTakenException extends RuntimeException {}
