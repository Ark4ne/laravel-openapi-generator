<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Responses;

use Ark4ne\OpenApi\Attributes\Response as ResponseAttribute;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\Http\Resources\CatResource;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\Http\Resources\DogResource;
use Illuminate\Http\JsonResponse;

final class ChildResponseController extends ParentResponseController
{
    #[ResponseAttribute(200, description: 'Fine')]
    public function inherited(): JsonResponse
    {
        return new JsonResponse();
    }

    #[ResponseAttribute(200, resource: CatResource::class)]
    #[ResponseAttribute(200, resource: DogResource::class)]
    public function twoBodies(): JsonResponse
    {
        return new JsonResponse();
    }
}
