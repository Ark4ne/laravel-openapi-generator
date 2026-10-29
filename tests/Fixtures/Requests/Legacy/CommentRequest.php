<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Requests\Legacy;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Rules of tests/app/Http/Requests/CommentRequest.php on branch v0.6.
 */
final class CommentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter' => 'array',
            'filter.user' => 'array',
            'filter.user.*' => 'integer',
            'filter.post' => 'array',
            'filter.post.*' => 'integer',
        ];
    }
}
