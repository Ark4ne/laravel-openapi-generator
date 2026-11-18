<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A plain form request whose rules read the current revision: `name` up to Initial, then `label`.
 */
final class ArticleRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ApiRevision::current()->isAtMost(ApiRevision::Initial)
            ? ['name' => 'required|string']
            : ['label' => 'required|string'];
    }
}
