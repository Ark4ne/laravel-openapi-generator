<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Requests\Legacy;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Rules of tests/app/Http/Requests/PostRequest.php on branch v0.6.
 */
final class PostRequest extends FormRequest
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
            'filter.has_comment' => 'boolean',
            'filter.date' => 'date|max:today',
        ];
    }
}
