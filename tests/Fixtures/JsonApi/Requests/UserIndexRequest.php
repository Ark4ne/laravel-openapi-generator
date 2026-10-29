<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Requests;

use Ark4ne\JsonApi\Requests\Rules\Fields;
use Ark4ne\JsonApi\Requests\Rules\Includes;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Resources\UserResource;
use Illuminate\Foundation\Http\FormRequest;

final class UserIndexRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'include' => [new Includes(UserResource::class)],
            'fields' => [new Fields(UserResource::class)],
            'filter.name' => 'string',
        ];
    }
}
