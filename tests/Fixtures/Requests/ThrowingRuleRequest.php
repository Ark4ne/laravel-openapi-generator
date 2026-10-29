<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ThrowingRuleRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['code' => [new ThrowingRule()]];
    }
}
