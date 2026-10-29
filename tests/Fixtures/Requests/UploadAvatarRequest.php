<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UploadAvatarRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'avatar' => 'required|image|max:1024',
            'photos.*' => 'mimes:jpg,png',
            'caption' => 'nullable|string',
        ];
    }
}
