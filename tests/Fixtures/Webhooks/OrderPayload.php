<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Webhooks;

use Illuminate\Foundation\Http\FormRequest;

final class OrderPayload extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'id' => 'required|integer',
            'total' => 'required|numeric',
        ];
    }
}
