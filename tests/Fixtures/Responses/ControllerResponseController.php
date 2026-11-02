<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Responses;

use Ark4ne\OpenApi\Attributes\Response as ResponseAttribute;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\Http\Resources\ArticleResource;
use Illuminate\Http\JsonResponse;
use LogicException;

/**
 * Responses declared on the controller, shared by its actions.
 */
#[ResponseAttribute(200, description: 'Shared success', type: 'array{id: int}')]
#[ResponseAttribute(401, description: 'Unauthenticated')]
final class ControllerResponseController
{
    public function plain(): JsonResponse
    {
        return new JsonResponse();
    }

    #[ResponseAttribute(201)]
    public function created(): JsonResponse
    {
        return new JsonResponse();
    }

    #[ResponseAttribute(401, description: 'Token expired')]
    public function overridden(): JsonResponse
    {
        return new JsonResponse();
    }

    #[ResponseAttribute(200, description: 'Fetched')]
    public function described(): JsonResponse
    {
        return new JsonResponse();
    }

    public function store(): ArticleResource
    {
        throw new LogicException();
    }
}
