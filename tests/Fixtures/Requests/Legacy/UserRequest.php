<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Requests\Legacy;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Rules of tests/app/Http/Requests/UserRequest.php on branch v0.6.
 */
final class UserRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter.*.name' => 'string',
            'filter.*.value' => 'array',
            'filter.*.value.*' => 'string',
        ];
    }
}
