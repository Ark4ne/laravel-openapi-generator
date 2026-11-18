<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Preparation;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A form request whose rules follow what the integrations prepared on it: one field per preparing integration.
 */
final class PreparedRequest extends FormRequest
{
    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        $prepared = $this->attributes->get('prepared');
        $fields = is_string($prepared) ? array_map(static fn(string $name): string => "prepared_{$name}", explode(',', $prepared)) : [];

        return ['name' => 'required|string', ...array_fill_keys($fields, 'string')];
    }
}
