<?php

namespace Test\app\Http\Requests;

use Ark4ne\JsonApi\Requests\Rules\Fields;
use Ark4ne\JsonApi\Requests\Rules\Includes;
use Illuminate\Foundation\Http\FormRequest;
use Test\app\Http\Resources\UserResource;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
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
