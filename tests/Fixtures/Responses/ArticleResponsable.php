<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Responses;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;

/**
 * Builds its own response.
 */
final class ArticleResponsable implements Responsable
{
    public function toResponse($request): JsonResponse
    {
        return new JsonResponse(['ok' => true], 202, ['X-Trace' => 'abc']);
    }
}
