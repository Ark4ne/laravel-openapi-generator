<?php

namespace Test\app\Http\Requests;

use Ark4ne\JsonApi\Requests\Rules\Fields;
use Ark4ne\JsonApi\Requests\Rules\Includes;
use Illuminate\Foundation\Http\FormRequest;
use Test\app\Http\Resources\PostResource;

class PostRequest extends FormRequest
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
            'filter' => 'array',
            'filter.user' => 'array',
            'filter.user.*' => 'integer',
            'filter.has_comment' => 'boolean',
            'filter.date' => 'date|max:today',
        ];
    }
}
