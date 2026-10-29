<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Extensions;

use Illuminate\Foundation\Http\FormRequest;

final class BrewRequest extends FormRequest
{
    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return ['blend' => 'required|uppercase'];
    }
}
