<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Responses;

use Ark4ne\OpenApi\Attributes\Response as ResponseAttribute;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\Http\Resources\AuthorResource;
use Illuminate\Http\JsonResponse;

abstract class ParentResponseController
{
    #[ResponseAttribute(200, resource: AuthorResource::class)]
    public function inherited(): JsonResponse
    {
        return new JsonResponse();
    }
}
