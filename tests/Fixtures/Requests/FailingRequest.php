<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Requests;

use Illuminate\Foundation\Http\FormRequest;
use RuntimeException;

final class FailingRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        throw new RuntimeException('database unavailable');
    }
}
