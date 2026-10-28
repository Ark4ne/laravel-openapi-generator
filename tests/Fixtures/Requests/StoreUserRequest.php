<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Requests;

use Ark4ne\OpenApi\Tests\Fixtures\Routing\Enums\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'unique:users,email'],
            'status' => [Rule::enum(Status::class)],
            'tags' => 'array|max:5',
            'tags.*' => 'string|distinct',
        ];
    }
}
