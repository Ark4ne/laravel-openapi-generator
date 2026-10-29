<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Responses\Exceptions;

use Ark4ne\OpenApi\Attributes\Response;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\Http\Resources\AuthorResource;
use RuntimeException;

#[Response(409, resource: AuthorResource::class, headers: ['Retry-After' => 'int'], summary: 'Busy')]
final class ResourcefulException extends RuntimeException {}
